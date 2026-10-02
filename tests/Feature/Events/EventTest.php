<?php

namespace Tests\Feature\Events;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Event;
use App\Models\Material;
use App\Models\Organisation;
use App\Models\User;
use App\Notifications\EventAssigned;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
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

    public function test_events_dashboard_reports_kpis(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $this->tenant()->runFor($org, function () use ($org) {
            Event::factory()->create(['organisation_id' => $org->id, 'status' => 'a_traiter', 'priority' => 'haute', 'type' => 'anomalie']);
            Event::factory()->create([
                'organisation_id' => $org->id,
                'status' => 'ferme',
                'type' => 'reparation',
                'created_at' => now()->subDays(5),
                'resolved_at' => now()->subDays(2),
            ]);
        });

        $this->actingAs($admin)->get('http://caserne.localhost/events/tableau-de-bord')
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Events/Dashboard')
                ->where('kpis.a_traiter', 1)
                ->where('kpis.high_priority', 1)
                ->where('kpis.resolved_30d', 1)
                ->has('statusChart', 4)
                ->has('typeChart', 3)
                ->has('recent', 2));
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

    public function test_moving_to_a_done_column_sets_resolved_at(): void
    {
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        [$event, $doneColumnId] = $this->tenant()->runFor($org, function () use ($org) {
            \App\Models\KanbanBoard::ensureSeeded($org);
            $event = Event::factory()->create(['organisation_id' => $org->id, 'status' => 'a_traiter']);
            $done = \App\Models\KanbanColumn::query()->where('is_done', true)->firstOrFail();

            return [$event, $done->id];
        });

        $this->actingAs($admin)->patch("http://caserne.localhost/events/{$event->id}/move", [
            'status' => $doneColumnId,
        ])->assertSessionHasNoErrors();

        $event->refresh();
        $this->assertSame('ferme', $event->status->value);
        $this->assertNotNull($event->resolved_at);
        $this->assertSame($doneColumnId, $event->kanban_column_id);
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

    public function test_assigning_an_event_notifies_the_assignee(): void
    {
        config(['mail.default' => 'array']);
        [$org, $admin] = $this->orgWithRole(Rbac::ADMIN);
        $assignee = $this->tenant()->runFor($org, function () use ($org) {
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole(Rbac::VERIFIER);

            return $u;
        });
        $event = Event::factory()->create(['organisation_id' => $org->id]);

        $this->actingAs($admin)->patch("http://caserne.localhost/events/{$event->id}", [
            'type' => 'anomalie', 'title' => $event->title, 'priority' => 'normale',
            'assigned_to' => $assignee->id,
        ])->assertSessionHasNoErrors();

        // Notification in-app…
        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $assignee->id,
            'type' => EventAssigned::class,
        ]);

        // …et e-mail brandé Vulkain à l'assigné (corps HTML décodé).
        $messages = \Illuminate\Support\Facades\Mail::mailer('array')->getSymfonyTransport()->messages();
        $this->assertNotEmpty($messages);
        $html = (string) $messages[count($messages) - 1]->getOriginalMessage()->getHtmlBody();
        $this->assertStringContainsString('VULKAIN', $html);
        $this->assertStringContainsString('#C6362B', $html);
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
