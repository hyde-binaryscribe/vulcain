<?php

namespace Tests\Feature\Fleet;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Document;
use App\Models\Organisation;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleSession;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DocumentTest extends TestCase
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

    public function test_admin_uploads_a_vehicle_document(): void
    {
        Storage::fake('local');
        $org = Organisation::factory()->slug('caserne')->create();
        [$admin, $vehicle] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $admin = User::factory()->create(['organisation_id' => $org->id]);
            $admin->assignRole(Rbac::ADMIN);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);

            return [$admin, $vehicle];
        });

        $this->actingAs($admin)->post('http://caserne.localhost/documents', [
            'subject_type' => 'vehicle',
            'subject_id' => $vehicle->id,
            'category' => 'Contrôle technique',
            'title' => 'CT 2026',
            'file' => UploadedFile::fake()->create('ct.pdf', 120, 'application/pdf'),
        ])->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, function () use ($vehicle) {
            $doc = Document::query()->where('title', 'CT 2026')->firstOrFail();
            $this->assertSame(Vehicle::class, $doc->documentable_type);
            $this->assertSame($vehicle->id, $doc->documentable_id);
            Storage::disk('local')->assertExists($doc->file_path);
        });
    }

    public function test_agent_in_service_consults_vehicle_document_with_reason(): void
    {
        Storage::fake('local');
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $docId] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $doc = $vehicle->documents()->create([
                'category' => 'Carte grise', 'title' => 'CG', 'file_path' => UploadedFile::fake()->create('cg.pdf', 10, 'application/pdf')->store('documents/'.$org->id, 'local'),
            ]);
            VehicleSession::create(['vehicle_id' => $vehicle->id, 'user_id' => $agent->id, 'opened_at' => now()]);

            return [$agent, $doc->id];
        });

        $this->actingAs($agent)->post("http://caserne.localhost/documents/{$docId}/consult", [
            'reason' => 'Contrôle routier',
        ])->assertSessionHasNoErrors();

        $this->actingAs($agent)->get("http://caserne.localhost/documents/{$docId}/file")->assertOk();

        $this->tenant()->runFor($org, function () use ($docId, $agent) {
            $this->assertDatabaseHas('document_accesses', [
                'document_id' => $docId, 'user_id' => $agent->id, 'reason' => 'Contrôle routier',
            ]);
        });
    }

    public function test_partner_of_open_session_can_consult_vehicle_document(): void
    {
        Storage::fake('local');
        $org = Organisation::factory()->slug('caserne')->create();
        [$partner, $docId] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $driver = User::factory()->create(['organisation_id' => $org->id]);
            $driver->assignRole(Rbac::VERIFIER);
            $partner = User::factory()->create(['organisation_id' => $org->id]);
            $partner->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $doc = $vehicle->documents()->create([
                'category' => 'Carte grise', 'title' => 'CG', 'file_path' => UploadedFile::fake()->create('cg.pdf', 10, 'application/pdf')->store('documents/'.$org->id, 'local'),
            ]);
            // Session ouverte par le conducteur, le binôme est l'équipier.
            VehicleSession::create(['vehicle_id' => $vehicle->id, 'user_id' => $driver->id, 'partner_user_id' => $partner->id, 'opened_at' => now()]);

            return [$partner, $doc->id];
        });

        $this->actingAs($partner)->get("http://caserne.localhost/documents/{$docId}/file")->assertOk();
    }

    public function test_agent_without_session_cannot_consult_vehicle_document(): void
    {
        Storage::fake('local');
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $docId] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER);
            $vehicle = Vehicle::factory()->create(['organisation_id' => $org->id]);
            $doc = $vehicle->documents()->create(['category' => 'Agrément', 'title' => 'Agr', 'file_path' => 'documents/x.pdf']);

            return [$agent, $doc->id];
        });

        $this->actingAs($agent)->get("http://caserne.localhost/documents/{$docId}/file")->assertForbidden();
    }

    public function test_personal_document_requires_consent(): void
    {
        Storage::fake('local');
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $docId] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id, 'documents_consent' => false]);
            $agent->assignRole(Rbac::VERIFIER);
            $doc = $agent->documents()->create(['category' => 'Diplôme', 'title' => 'DEA', 'file_path' => UploadedFile::fake()->create('dea.pdf', 10, 'application/pdf')->store('documents/'.$org->id, 'local')]);

            return [$agent, $doc->id];
        });

        // Sans consentement → refusé.
        $this->actingAs($agent)->get("http://caserne.localhost/documents/{$docId}/file")->assertForbidden();

        // L'agent autorise, puis peut consulter.
        $this->actingAs($agent)->post('http://caserne.localhost/documents/consent', ['consent' => true])->assertSessionHasNoErrors();
        $this->actingAs($agent)->get("http://caserne.localhost/documents/{$docId}/file")->assertOk();
    }
}
