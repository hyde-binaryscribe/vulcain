<?php

namespace Tests\Feature\History;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\ActivityLog;
use App\Models\Material;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityLogTest extends TestCase
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

    public function test_creating_and_updating_a_material_is_recorded(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();

        $this->tenant()->runFor($org, function () use ($org) {
            $material = Material::factory()->create(['organisation_id' => $org->id, 'name' => 'Collier']);

            $this->assertDatabaseHas('activity_logs', [
                'subject_type' => Material::class,
                'subject_id' => $material->id,
                'action' => 'created',
            ]);

            $material->update(['name' => 'Collier renforcé']);

            $log = ActivityLog::query()->where('subject_id', $material->id)->where('action', 'updated')->first();
            $this->assertNotNull($log);
            $this->assertSame('Collier renforcé', $log->properties['new']['name']);
            $this->assertSame('Collier', $log->properties['old']['name']);
        });
    }

    public function test_activity_is_scoped_per_organisation(): void
    {
        $orgA = Organisation::factory()->slug('a')->create();
        $orgB = Organisation::factory()->slug('b')->create();

        $this->tenant()->runFor($orgA, fn () => Material::factory()->create(['organisation_id' => $orgA->id]));
        $this->tenant()->runFor($orgB, fn () => Material::factory()->create(['organisation_id' => $orgB->id]));

        $countA = $this->tenant()->runFor($orgA, fn () => ActivityLog::query()->count());
        $countB = $this->tenant()->runFor($orgB, fn () => ActivityLog::query()->count());

        $this->assertSame(1, $countA);
        $this->assertSame(1, $countB);
    }

    public function test_history_page_is_accessible_with_permission(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $verifier = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole(Rbac::VERIFIER); // le vérificateur a la permission history.view

            return $u;
        });

        $this->actingAs($verifier)->get('http://caserne.localhost/activity')->assertOk();
    }

    public function test_actor_is_recorded_when_authenticated(): void
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $admin = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id, 'name' => 'Chef']);
            $u->assignRole(Rbac::ADMIN);

            return $u;
        });

        $this->actingAs($admin)->post('http://caserne.localhost/materials', [
            'name' => 'Nouveau matériel',
            'tracking_mode' => 'quantity',
            'status' => 'conforme',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('activity_logs', [
            'organisation_id' => $org->id,
            'actor_name' => 'Chef',
            'action' => 'created',
        ]);
    }
}
