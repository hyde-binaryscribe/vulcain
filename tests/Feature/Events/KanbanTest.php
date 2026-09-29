<?php

namespace Tests\Feature\Events;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Event;
use App\Models\KanbanBoard;
use App\Models\KanbanColumn;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class KanbanTest extends TestCase
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
    private function adminOrg(): array
    {
        $org = Organisation::factory()->slug('caserne')->create();
        $admin = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $u = User::factory()->create(['organisation_id' => $org->id]);
            $u->assignRole(Rbac::ADMIN);

            return $u;
        });

        return [$org, $admin];
    }

    public function test_admin_can_create_a_board_with_default_columns(): void
    {
        [$org, $admin] = $this->adminOrg();

        $this->actingAs($admin)->post('http://caserne.localhost/kanban/boards', ['name' => 'Réparations'])
            ->assertRedirect();

        $this->tenant()->runFor($org, function () {
            $board = KanbanBoard::query()->where('name', 'Réparations')->firstOrFail();
            $this->assertSame(2, $board->columns()->count()); // À faire + Terminé
        });
    }

    public function test_admin_can_add_a_column_to_a_board(): void
    {
        [$org, $admin] = $this->adminOrg();
        $boardId = $this->tenant()->runFor($org, function () use ($org) {
            KanbanBoard::ensureSeeded($org);

            return KanbanBoard::query()->firstOrFail()->id;
        });

        $this->actingAs($admin)->post("http://caserne.localhost/kanban/boards/{$boardId}/columns", ['name' => 'En attente pièce'])
            ->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, function () use ($boardId) {
            $this->assertDatabaseHas('kanban_columns', ['kanban_board_id' => $boardId, 'name' => 'En attente pièce']);
        });
    }

    public function test_new_anomaly_lands_in_the_configured_entry_column(): void
    {
        [$org, $admin] = $this->adminOrg();
        $entryColumnId = $this->tenant()->runFor($org, function () use ($org) {
            KanbanBoard::ensureSeeded($org);
            // On désigne « En cours » comme colonne d'entrée.
            $col = KanbanColumn::query()->where('name', 'En cours')->firstOrFail();
            $org->settings = array_merge($org->settings ?? [], ['anomaly_entry_column_id' => $col->id]);
            $org->save();

            return $col->id;
        });

        $this->actingAs($admin)->post('http://caserne.localhost/events', [
            'type' => 'anomalie', 'title' => 'Feu arrière HS', 'priority' => 'normale',
        ])->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, function () use ($entryColumnId) {
            $event = Event::query()->where('title', 'Feu arrière HS')->firstOrFail();
            $this->assertSame($entryColumnId, $event->kanban_column_id);
        });
    }
}
