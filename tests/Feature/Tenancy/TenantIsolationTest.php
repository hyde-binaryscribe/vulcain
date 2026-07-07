<?php

namespace Tests\Feature\Tenancy;

use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\Exceptions\TenancyContextMissingException;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private function context(): TenantContext
    {
        return app(TenantContext::class);
    }

    protected function tearDown(): void
    {
        $this->context()->forget();
        parent::tearDown();
    }

    public function test_reads_are_scoped_to_the_current_organisation(): void
    {
        $orgA = Organisation::factory()->create();
        $orgB = Organisation::factory()->create();
        $userA = User::factory()->create(['organisation_id' => $orgA->id]);
        $userB = User::factory()->create(['organisation_id' => $orgB->id]);

        $this->context()->set($orgA);
        $this->assertSame(1, User::count());
        $this->assertTrue(User::whereKey($userA->id)->exists());
        // Fuite inter-tenant / IDOR : l'utilisateur de B est invisible depuis A.
        $this->assertNull(User::find($userB->id));

        $this->context()->set($orgB);
        $this->assertEquals([$userB->id], User::pluck('id')->all());
    }

    public function test_creating_fills_organisation_id_from_context(): void
    {
        $org = Organisation::factory()->create();
        $this->context()->set($org);

        $user = new User(['first_name' => 'Jean', 'last_name' => 'Test', 'name' => 'Jean Test', 'email' => 'test@example.test', 'password' => 'motdepasse123']);
        $user->save();

        $this->assertSame($org->id, $user->fresh()->organisation_id);
    }

    public function test_creating_without_context_throws(): void
    {
        $this->expectException(TenancyContextMissingException::class);

        $user = new User(['first_name' => 'Jean', 'last_name' => 'Test', 'name' => 'Jean Test', 'email' => 'test@example.test', 'password' => 'motdepasse123']);
        $user->organisation_id = null;
        $user->save();
    }

    public function test_cross_tenant_mode_bypasses_the_scope(): void
    {
        User::factory()->create(['organisation_id' => Organisation::factory()->create()->id]);
        User::factory()->create(['organisation_id' => Organisation::factory()->create()->id]);

        $count = $this->context()->runCrossTenant(fn () => User::count());

        $this->assertSame(2, $count);
    }

    public function test_run_for_scopes_to_a_given_organisation(): void
    {
        $orgA = Organisation::factory()->create();
        $orgB = Organisation::factory()->create();
        User::factory()->create(['organisation_id' => $orgA->id]);
        User::factory()->create(['organisation_id' => $orgB->id]);

        $this->assertSame(1, $this->context()->runFor($orgA, fn () => User::count()));
        $this->assertSame(1, $this->context()->runFor($orgB, fn () => User::count()));
    }
}
