<?php

namespace Tests\Unit;

use App\Models\Schedule;
use App\Models\User;
use App\Policies\SchedulePolicy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SchedulePolicyTest extends TestCase
{
    use RefreshDatabase;

    private SchedulePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new SchedulePolicy();
    }

    public function test_rh_and_directeur_can_create_schedules(): void
    {
        $rh = User::factory()->create(['role' => 'rh']);
        $directeur = User::factory()->create(['role' => 'directeur']);

        $this->assertTrue($this->policy->create($rh));
        $this->assertTrue($this->policy->create($directeur));
    }

    public function test_employee_and_comptable_cannot_create_schedules(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);
        $comptable = User::factory()->create(['role' => 'comptable']);

        $this->assertFalse($this->policy->create($employee));
        $this->assertFalse($this->policy->create($comptable));
    }

    public function test_employee_can_only_view_their_own_schedule(): void
    {
        $employee = User::factory()->create(['role' => 'employe']);
        $otherEmployee = User::factory()->create(['role' => 'employe']);

        $ownSchedule = Schedule::create([
            'user_id' => $employee->id,
            'created_by' => $employee->id,
            'title' => 'Journée standard',
            'date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $othersSchedule = Schedule::create([
            'user_id' => $otherEmployee->id,
            'created_by' => $otherEmployee->id,
            'title' => 'Journée standard',
            'date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $this->assertTrue($this->policy->view($employee, $ownSchedule));
        $this->assertFalse($this->policy->view($employee, $othersSchedule));
    }

    public function test_rh_can_view_any_schedule(): void
    {
        $rh = User::factory()->create(['role' => 'rh']);
        $employee = User::factory()->create(['role' => 'employe']);

        $schedule = Schedule::create([
            'user_id' => $employee->id,
            'created_by' => $rh->id,
            'title' => 'Journée standard',
            'date' => now()->toDateString(),
            'start_time' => '08:00',
            'end_time' => '17:00',
        ]);

        $this->assertTrue($this->policy->view($rh, $schedule));
    }
}