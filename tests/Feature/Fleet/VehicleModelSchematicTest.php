<?php

namespace Tests\Feature\Fleet;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\Organisation;
use App\Models\User;
use App\Models\VehicleModel;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class VehicleModelSchematicTest extends TestCase
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

    public function test_manager_uploads_and_serves_a_model_schematic(): void
    {
        Storage::fake('local');
        $org = Organisation::factory()->slug('caserne')->create();
        [$manager, $model] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $manager = User::factory()->create(['organisation_id' => $org->id]);
            $manager->assignRole(Rbac::ADMIN);
            $model = VehicleModel::create(['organisation_id' => $org->id, 'name' => 'Renault Master · GIFA', 'brand' => 'Renault', 'model' => 'Master']);

            return [$manager, $model];
        });

        $this->actingAs($manager)->post("http://caserne.localhost/vehicle-models/{$model->id}/schematic", [
            'view' => 'gauche',
            'image' => UploadedFile::fake()->image('cote-gauche.png', 800, 400),
        ])->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, function () use ($model) {
            $schematic = $model->schematics()->where('view', 'gauche')->firstOrFail();
            Storage::disk('local')->assertExists($schematic->image_path);
        });

        $this->actingAs($manager)->get("http://caserne.localhost/vehicle-models/{$model->id}/schematic/gauche")->assertOk();
    }

    public function test_uploading_same_view_replaces_previous_image(): void
    {
        Storage::fake('local');
        $org = Organisation::factory()->slug('caserne')->create();
        [$manager, $model] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $manager = User::factory()->create(['organisation_id' => $org->id]);
            $manager->assignRole(Rbac::ADMIN);
            $model = VehicleModel::create(['organisation_id' => $org->id, 'name' => 'Master', 'brand' => 'Renault', 'model' => 'Master']);

            return [$manager, $model];
        });

        $this->actingAs($manager)->post("http://caserne.localhost/vehicle-models/{$model->id}/schematic", [
            'view' => 'avant', 'image' => UploadedFile::fake()->image('a.png'),
        ]);
        $this->actingAs($manager)->post("http://caserne.localhost/vehicle-models/{$model->id}/schematic", [
            'view' => 'avant', 'image' => UploadedFile::fake()->image('b.png'),
        ])->assertSessionHasNoErrors();

        $this->tenant()->runFor($org, function () use ($model) {
            $this->assertSame(1, $model->schematics()->where('view', 'avant')->count());
        });
    }

    public function test_agent_cannot_upload_schematic(): void
    {
        Storage::fake('local');
        $org = Organisation::factory()->slug('caserne')->create();
        [$agent, $model] = $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $agent = User::factory()->create(['organisation_id' => $org->id]);
            $agent->assignRole(Rbac::VERIFIER);
            $model = VehicleModel::create(['organisation_id' => $org->id, 'name' => 'Master', 'brand' => 'Renault', 'model' => 'Master']);

            return [$agent, $model];
        });

        $this->actingAs($agent)->post("http://caserne.localhost/vehicle-models/{$model->id}/schematic", [
            'view' => 'gauche', 'image' => UploadedFile::fake()->image('x.png'),
        ])->assertForbidden();
    }
}
