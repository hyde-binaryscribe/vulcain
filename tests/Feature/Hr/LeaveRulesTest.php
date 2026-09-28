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

    public function test_balance_reflects_approved_paid_leave(): void
    {
        [$org, $manager] = $this->orgWithManager();

        $employee = $this->tenant()->runFor($org, function () use ($org) {
            LeaveRule::create(['job_role' => 'ade', 'annual_days' => 25]);
            $u = User::factory()->create(['organisation_id' => $org->id, 'job_role' => 'ade']);

            $year = Carbon::now()->year;
            LeaveRequest::create([
                'user_id' => $u->id,
                'type' => 'conge_paye',
                'start_date' => Carbon::create($year, 6, 1),
                'end_date' => Carbon::create($year, 6, 5), // 5 jours
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

    public function test_entitlement_is_prorated_from_hire_date(): void
    {
        [$org, $manager] = $this->orgWithManager();
        $year = Carbon::now()->year;

        $employee = $this->tenant()->runFor($org, function () use ($org, $year) {
            LeaveRule::create(['job_role' => 'ade', 'annual_days' => 24]);

            return User::factory()->create([
                'organisation_id' => $org->id,
                'job_role' => 'ade',
                'hire_date' => Carbon::create($year, 7, 1), // arrivé à mi-année
            ]);
        });

        $this->actingAs($employee)->get('http://ambu.localhost/leave')
            ->assertInertia(fn (AssertableInertia $p) => $p
                ->where('myBalance.annual_full', 24)
                ->where('myBalance.annual_days', fn ($v) => $v > 0 && $v < 24));
    }
}
