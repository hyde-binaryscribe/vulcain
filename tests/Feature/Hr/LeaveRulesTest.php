<?php

namespace Tests\Feature\Hr;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Domain\Sectors\Sector;
use App\Models\LeaveRequest;
use App\Models\LeaveRule;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class LeaveRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['tenancy.central_domains' => ['localhost']]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        app(TenantContext::class)->forget();
        parent::tearDown();
    }

    private function tenant(): TenantContext
    {
        return app(TenantContext::class);
    }

    /** @return array{Organisation, User} */
    private function orgWithManager(): array
    {
        $org = Organisation::factory()->slug('ambu')->create(['sector' => Sector::AMBULANCE_PRIVEE]);
        $manager = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole(Rbac::ADMIN);

            return $u;
        });

        return [$org, $manager];
    }

    public function test_manager_view_exposes_calendar_rules_and_team(): void
    {
        [, $manager] = $this->orgWithManager();

        $this->actingAs($manager)->get('http://ambu.localhost/leave')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->component('Leave/Index')
                ->where('canManage', true)
                ->has('calendar')
                ->has('rules')
                ->has('team')
                ->has('month'));
    }

    public function test_manager_can_save_rules(): void
    {
        [$org, $manager] = $this->orgWithManager();

        $this->actingAs($manager)->post('http://ambu.localhost/leave/rules', [
            'rules' => [
                ['job_role' => 'ade', 'max_simultaneous' => 2, 'annual_days' => 25],
            ],
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leave_rules', [
            'organisation_id' => $org->id,
            'job_role' => 'ade',
            'max_simultaneous' => 2,
            'annual_days' => 25,
        ]);
    }

    public function test_balance_reflects_approved_paid_leave_in_working_days(): void
    {
        Carbon::setTestNow('2026-09-15'); // période mai 2026 → avril 2027
        [$org, $manager] = $this->orgWithManager();

        $employee = $this->tenant()->runFor($org, function () use ($org) {
            LeaveRule::create(['job_role' => 'ade', 'annual_days' => 25]);
            $u = User::factory()->create(['organisation_id' => $org->id, 'job_role' => 'ade']);

            LeaveRequest::create([
                'user_id' => $u->id,
                'type' => 'conge_paye',
                'start_date' => '2026-09-07', // lundi
                'end_date' => '2026-09-11',   // vendredi → 5 jours ouvrables
                'status' => 'approuve',
            ]);

            return $u;
        });

        $this->actingAs($employee)->get('http://ambu.localhost/leave')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('myBalance.consumed', 5)
                ->where('myBalance.annual_days', 25)
                ->where('myBalance.remaining', 20));
    }

    public function test_default_entitlement_is_thirty_working_days_without_rule(): void
    {
        Carbon::setTestNow('2026-09-15');
        [$org] = $this->orgWithManager();

        // Aucun métier, aucune règle : tout le monde a un solde (2,5 j/mois = 30 j).
        $employee = $this->tenant()->runFor($org, fn () => User::factory()->create(['organisation_id' => $org->id]));

        $this->actingAs($employee)->get('http://ambu.localhost/leave')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('myBalance.annual_days', 30)
                ->where('myBalance.monthly_rate', 2.5)
                ->where('myBalance.remaining', 30));
    }

    public function test_sundays_are_excluded_from_consumption(): void
    {
        Carbon::setTestNow('2026-09-15');
        [$org] = $this->orgWithManager();

        $employee = $this->tenant()->runFor($org, function () use ($org) {
            $u = User::factory()->create(['organisation_id' => $org->id]);
            LeaveRequest::create([
                'user_id' => $u->id,
                'type' => 'conge_paye',
                'start_date' => '2026-09-07', // lundi
                'end_date' => '2026-09-13',   // dimanche → 6 ouvrables (dimanche exclu)
                'status' => 'approuve',
            ]);

            return $u;
        });

        $this->actingAs($employee)->get('http://ambu.localhost/leave')
            ->assertInertia(fn (AssertableInertia $p) => $p->where('myBalance.consumed', 6));
    }

    public function test_entitlement_is_prorated_from_hire_date(): void
    {
        Carbon::setTestNow('2026-09-15'); // référence : mai 2025 → avril 2026
        [$org] = $this->orgWithManager();

        $employee = $this->tenant()->runFor($org, function () use ($org) {
            LeaveRule::create(['job_role' => 'ade', 'annual_days' => 24]); // 2 j/mois
            return User::factory()->create([
                'organisation_id' => $org->id,
                'job_role' => 'ade',
                'hire_date' => '2025-11-01', // présent nov→avr = 6 mois → 12 j
            ]);
        });

        $this->actingAs($employee)->get('http://ambu.localhost/leave')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('myBalance.annual_full', 24)
                ->where('myBalance.annual_days', 12));
    }
}
