<?php

use App\Models\Personnel;
use App\Models\Project;
use App\Models\ProjectSubtask;
use App\Models\ProjectTask;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Inventory Staff', 'guard_name' => 'web']);
});

test('admin and manager can access updated dashboard with recent projects, manpower stats, and task overview', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    // Create projects and ensure $project1 is the most recently updated
    $otherProjects = Project::factory()->count(2)->create([
        'created_by' => $manager->id,
        'status' => 'ongoing',
        'updated_at' => now()->subHours(2),
    ]);

    $project1 = Project::factory()->create([
        'name' => 'Metro Elevator Installation',
        'created_by' => $manager->id,
        'status' => 'ongoing',
        'updated_at' => now(),
    ]);

    $task = ProjectTask::create([
        'project_id' => $project1->id,
        'task_name' => 'Structural Framing',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(7)->toDateString(),
        'status' => 'in_progress',
        'created_by' => $manager->id,
    ]);

    ProjectSubtask::create([
        'project_task_id' => $task->id,
        'title' => 'Pit inspection',
        'is_completed' => true,
        'due_date' => now()->toDateString(),
    ]);

    ProjectSubtask::create([
        'project_task_id' => $task->id,
        'title' => 'Guide rails mounting',
        'is_completed' => false,
        'due_date' => now()->addDays(2)->toDateString(),
    ]);

    // Create field personnel with expertise and project assignment
    Personnel::create([
        'employee_id' => 'EMP-2026101',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'date_of_birth' => '1990-05-15',
        'address' => '123 Site St, Manila',
        'expertise' => Personnel::EXPERTISE_SITE_ENGINEER,
        'project_id' => $project1->id,
        'date_assigned' => now()->toDateString(),
        'created_by' => $manager->id,
    ]);

    Personnel::create([
        'employee_id' => 'EMP-2026102',
        'first_name' => 'Maria',
        'last_name' => 'Santos',
        'date_of_birth' => '1992-08-20',
        'address' => '456 Safety Rd, Quezon City',
        'expertise' => Personnel::EXPERTISE_SAFETY_OFFICER,
        'project_id' => null,
        'created_by' => $manager->id,
    ]);

    $response = $this->actingAs($manager)->get(route('dashboard'));

    $response->assertStatus(200);

    // Verify retained and enhanced status summary cards clickable routes
    $response->assertSee(route('projects.index'));
    $response->assertSee(route('projects.index', ['status' => 'ongoing']));
    $response->assertSee(route('projects.index', ['status' => 'pending']));
    $response->assertSee(route('projects.index', ['status' => 'completed']));

    // Verify Recent Projects & Progression section
    $response->assertSee('Recent Projects &amp; Progression', false);
    $response->assertSee('progress-bar-container');
    $response->assertSee($project1->name);
    $response->assertSee($project1->project_code);
    $response->assertSee(route('projects.show', $project1));

    // Verify Task Overview section
    $response->assertSee('Task Overview');
    $response->assertSee('Total Tasks');
    $response->assertSee('In Progress');
    $response->assertSee('Checklist Execution Rate');

    // Verify Manpower Statistics section
    $response->assertSee('Manpower Statistics');
    $response->assertSee('Total Field Workers');
    $response->assertSee('Currently Assigned');
    $response->assertSee('Available for Deployment');
    $response->assertSee(Personnel::EXPERTISE_SITE_ENGINEER);
});

test('inventory staff cannot access analytical dashboard', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Inventory Staff');

    $response = $this->actingAs($staff)->get(route('dashboard'));

    $response->assertStatus(403);
});
