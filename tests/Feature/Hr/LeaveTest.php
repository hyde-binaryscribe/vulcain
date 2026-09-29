<?php

namespace Tests\Feature\Hr;

use App\Domain\Identity\Rbac;
use App\Domain\Identity\RoleProvisioner;
use App\Models\LeaveRequest;
use App\Models\Organisation;
use App\Models\User;
use App\Support\Tenancy\TenantContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LeaveTest extends TestCase
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

    /** @return array{Organisation, User, User} */
    private function orgWithUsers(): array
    {
        $org = Organisation::factory()->slug('caserne')->create();

        return $this->tenant()->runFor($org, function () use ($org) {
            app(RoleProvisioner::class)->provision($org);
            $manager = User::factory()->create(['organisation_id' => $org->id]);
            $manager->assignRole(Rbac::ADMIN);
            $employee = User::factory()->create(['organisation_id' => $org->id]);

            return [$org, $manager, $employee];
        });
    }

    public function test_employee_can_submit_a_request(): void
    {
        [$org, , $employee] = $this->orgWithUsers();

        $this->actingAs($employee)->post('http://caserne.localhost/leave', [
            'type' => 'conge_paye',
            'start_date' => '2027-02-01',
            'end_date' => '2027-02-05',
            'reason' => 'Vacances',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leave_requests', [
            'organisation_id' => $org->id,
            'user_id' => $employee->id,
            'type' => 'conge_paye',
            'status' => 'en_attente',
        ]);
    }

    public function test_end_before_start_is_rejected(): void
    {
        [, , $employee] = $this->orgWithUsers();

        $this->actingAs($employee)->post('http://caserne.localhost/leave', [
            'type' => 'conge_paye',
            'start_date' => '2027-02-05',
            'end_date' => '2027-02-01',
        ])->assertSessionHasErrors('end_date');
    }

    public function test_manager_can_approve_and_requester_is_notified(): void
    {
        [$org, $manager, $employee] = $this->orgWithUsers();

        $leave = $this->tenant()->runFor($org, fn () => LeaveRequest::create([
            'user_id' => $employee->id,
            'type' => 'rtt',
            'start_date' => '2027-03-01',
            'end_date' => '2027-03-01',
            'status' => 'en_attente',
        ]));

        $this->actingAs($manager)->post("http://caserne.localhost/leave/{$leave->id}/decision", [
            'action' => 'approve',
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leave_requests', [
            'id' => $leave->id,
            'status' => 'approuve',
            'reviewer_id' => $manager->id,
        ]);
        $this->assertSame(1, $employee->fresh()->notifications()->count());
    }

    public function test_employee_cannot_decide(): void
    {
        [$org, , $employee] = $this->orgWithUsers();
        $other = $this->tenant()->runFor($org, fn () => User::factory()->create(['organisation_id' => $org->id]));

        $leave = $this->tenant()->runFor($org, fn () => LeaveRequest::create([
            'user_id' => $other->id,
            'type' => 'rtt',
            'start_date' => '2027-03-01',
            'end_date' => '2027-03-01',
            'status' => 'en_attente',
        ]));

        $this->actingAs($employee)->post("http://caserne.localhost/leave/{$leave->id}/decision", [
            'action' => 'approve',
        ])->assertForbidden();
    }

    public function test_owner_can_cancel_pending_request(): void
    {
        [$org, , $employee] = $this->orgWithUsers();
        $leave = $this->tenant()->runFor($org, fn () => LeaveRequest::create([
            'user_id' => $employee->id,
            'type' => 'absence',
            'start_date' => '2027-03-01',
            'end_date' => '2027-03-02',
            'status' => 'en_attente',
        ]));

        $this->actingAs($employee)->post("http://caserne.localhost/leave/{$leave->id}/cancel")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'annule']);
    }

    public function test_owner_can_cancel_approved_request_and_managers_are_notified(): void
    {
        [$org, $manager, $employee] = $this->orgWithUsers();
        $leave = $this->tenant()->runFor($org, fn () => LeaveRequest::create([
            'user_id' => $employee->id,
            'type' => 'conge_paye',
            'start_date' => '2027-04-01',
            'end_date' => '2027-04-05',
            'status' => 'approuve',
            'reviewer_id' => $manager->id,
            'decided_at' => now(),
        ]));

        $this->actingAs($employee)->post("http://caserne.localhost/leave/{$leave->id}/cancel")
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'annule']);
        $this->assertSame(1, $manager->fresh()->notifications()->count());
    }

    public function test_owner_can_modify_request_which_returns_to_pending_flagged_modified(): void
    {
        [$org, $manager, $employee] = $this->orgWithUsers();
        $leave = $this->tenant()->runFor($org, fn () => LeaveRequest::create([
            'user_id' => $employee->id,
            'type' => 'conge_paye',
            'start_date' => '2027-05-01',
            'end_date' => '2027-05-03',
            'status' => 'approuve',
            'reviewer_id' => $manager->id,
            'decided_at' => now(),
        ]));

        $this->actingAs($employee)->patch("http://caserne.localhost/leave/{$leave->id}", [
            'type' => 'conge_paye',
            'start_date' => '2027-05-02',
            'end_date' => '2027-05-06',
        ])->assertSessionHasNoErrors();

        $fresh = $leave->fresh();
        $this->assertSame('en_attente', $fresh->status->value);
        $this->assertNull($fresh->reviewer_id);
        $this->assertNull($fresh->decided_at);
        $this->assertNotNull($fresh->modified_at);
        $this->assertSame('2027-05-06', $fresh->end_date->toDateString());
        $this->assertSame(1, $manager->fresh()->notifications()->count());
    }

    public function test_owner_cannot_modify_someone_elses_request(): void
    {
        [$org, , $employee] = $this->orgWithUsers();
        $other = $this->tenant()->runFor($org, fn () => User::factory()->create(['organisation_id' => $org->id]));
        $leave = $this->tenant()->runFor($org, fn () => LeaveRequest::create([
            'user_id' => $other->id,
            'type' => 'rtt',
            'start_date' => '2027-06-01',
            'end_date' => '2027-06-02',
            'status' => 'en_attente',
        ]));

        $this->actingAs($employee)->patch("http://caserne.localhost/leave/{$leave->id}", [
            'type' => 'rtt',
            'start_date' => '2027-06-01',
            'end_date' => '2027-06-03',
        ])->assertForbidden();
    }

    public function test_refused_request_can_no_longer_be_cancelled(): void
    {
        [$org, , $employee] = $this->orgWithUsers();
        $leave = $this->tenant()->runFor($org, fn () => LeaveRequest::create([
            'user_id' => $employee->id,
            'type' => 'rtt',
            'start_date' => '2027-07-01',
            'end_date' => '2027-07-02',
            'status' => 'refuse',
        ]));

        $this->actingAs($employee)->post("http://caserne.localhost/leave/{$leave->id}/cancel")
            ->assertStatus(422);

        $this->assertDatabaseHas('leave_requests', ['id' => $leave->id, 'status' => 'refuse']);
    }
}
