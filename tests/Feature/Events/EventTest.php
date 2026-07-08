<?php

namespace Tests\Feature\Events;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Event;
use App\Models\Material;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventTest extends TestCase
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
    private function orgWithRole(string $role): array
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $user = $this->tenant()->runFor($org, function () use ($org, $role) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole($role);

            return $u;
        });

        return [$org, $user];
    }

    public function test_admin_can_create_an_event(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);

        $this->actingAs($admin)->post('http://caserne.localhost/events', [
            'type' => 'reparation',
            'title' => 'Remplacer bouteille O2',
            'priority' => 'haute',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('events', [
            'organisation_id' => $org->id,
            'title' => 'Remplacer bouteille O2',
            'status' => 'a_traiter',
            'created_by' => $admin->id,
        ]);
    }

    public function test_moving_to_closed_column_sets_resolved_at(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $event = Event::factory()->create(['organisation_id' => $org->id, 'status' => 'a_traiter']);

        $this->actingAs($admin)->patch("http://caserne.localhost/events/{$event->id}/move", [
            'status' => 'resolu',
        ])->assertSessionHasNoErrors();

        $event->refresh();
        $this->assertSame('resolu', $event->status->value);
        $this->assertNotNull($event->resolved_at);
    }

    public function test_can_add_a_comment(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $event = Event::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($admin)->post("http://caserne.localhost/events/{$event->id}/comments", [
            'body' => 'Pièce commandée',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('event_comments', [
            'event_id' => $event->id,
            'user_id' => $admin->id,
            'body' => 'Pièce commandée',
        ]);
    }

    public function test_can_update_linked_material_status(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $material = Material::factory()->create(['organisation_id' => $org->id, 'status' => 'conforme']);
        $event = Event::factory()->create(['organisation_id' => $org->id, 'material_id' => $material->id]);

        $this->actingAs($admin)->patch("http://caserne.localhost/events/{$event->id}/material-status", [
            'status' => 'en_reparation',
        ])->assertSessionHasNoErrors();

        $this->assertSame('en_reparation', $material->refresh()->status->value);
    }
}
