<?php

use App\Models\Project;
use App\Models\ProjectSubtask;
use App\Models\ProjectTask;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('creating a project automatically initializes default pre-construction major task with 4 sub tasks and due dates', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $startDate = now()->addDay()->format('Y-m-d');
    $targetDate = now()->addMonths(2)->format('Y-m-d');

    $this->actingAs($manager)
        ->post(route('projects.store'), [
            'name' => 'Ayala Center Elevator Overhaul',
            'client_name' => 'Ayala Land Inc',
            'address' => 'Makati Commercial Center',
            'barangay' => 'San Lorenzo',
            'city' => 'Makati City',
            'project_type' => 'Commercial',
            'payment_status' => 'Partial',
            'start_date' => $startDate,
            'target_completion_date' => $targetDate,
            'contract_price' => 2000000.00,
        ])
        ->assertRedirect();

    $project = Project::firstOrFail();

    expect($project->tasks)->toHaveCount(1);

    $defaultTask = $project->tasks->first();
    expect($defaultTask->task_name)->toBe('Pre-construction & Regulatory Compliance')
        ->and($defaultTask->description)->toBe('Securing required permits, obtaining design approvals, verifying site readiness, and preparing the installation area prior to elevator construction and mobilization.')
        ->and($defaultTask->start_date->format('Y-m-d'))->toBe($startDate)
        ->and($defaultTask->end_date->format('Y-m-d'))->toBe(now()->addDay()->addDays(6)->format('Y-m-d'))
        ->and($defaultTask->subtasks)->toHaveCount(4)
        ->and($defaultTask->subtasks[0]->title)->toBe('LGU building & mechanical permits')
        ->and($defaultTask->subtasks[0]->due_date->format('Y-m-d'))->toBe(now()->addDay()->addDays(1)->format('Y-m-d'))
        ->and($defaultTask->subtasks[1]->title)->toBe('Approval of project design / plan')
        ->and($defaultTask->subtasks[1]->due_date->format('Y-m-d'))->toBe(now()->addDay()->addDays(2)->format('Y-m-d'))
        ->and($defaultTask->subtasks[2]->title)->toBe('Site inspection and readiness assessment')
        ->and($defaultTask->subtasks[2]->due_date->format('Y-m-d'))->toBe(now()->addDay()->addDays(4)->format('Y-m-d'))
        ->and($defaultTask->subtasks[3]->title)->toBe('Initial site mobilization and safety barricade staging')
        ->and($defaultTask->subtasks[3]->due_date->format('Y-m-d'))->toBe(now()->addDay()->addDays(6)->format('Y-m-d'))
        ->and($defaultTask->progress_percentage)->toBe(0);
});

test('manager can manually create a major task with week-based dates and optional sub tasks with dates', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create([
        'start_date' => now()->format('Y-m-d'),
        'target_completion_date' => now()->addMonths(2)->format('Y-m-d'),
    ]);

    $week2Start = now()->addWeeks(1)->format('Y-m-d');
    $week2End = now()->addWeeks(1)->addDays(6)->format('Y-m-d');
    $subtaskDate1 = now()->addWeeks(1)->addDays(2)->format('Y-m-d');

    $this->actingAs($manager)
        ->post(route('projects.tasks.store', $project), [
            'task_name' => 'Week 2 - Mechanical Guide Rail Installation',
            'start_date' => $week2Start,
            'end_date' => $week2End,
            'description' => 'Guide rail laser plumb alignment, bracket anchoring, machine room pedestal mounting.',
            'subtasks' => [
                'Laser verticality plumb test',
                'Guide rail bracket anchoring',
                'Counterweight frame mounting',
            ],
            'subtask_dates' => [
                $subtaskDate1,
                null,
                null,
            ],
        ])
        ->assertRedirect(route('projects.show', $project));

    $task = ProjectTask::where('project_id', $project->id)
        ->where('task_name', 'Week 2 - Mechanical Guide Rail Installation')
        ->firstOrFail();

    expect($task->start_date->format('Y-m-d'))->toBe($week2Start)
        ->and($task->end_date->format('Y-m-d'))->toBe($week2End)
        ->and($task->subtasks)->toHaveCount(3)
        ->and($task->subtasks->first()->title)->toBe('Laser verticality plumb test')
        ->and($task->subtasks->first()->due_date?->format('Y-m-d'))->toBe($subtaskDate1);
});

test('manager can update major task name, dates, and description', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create();
    $task = ProjectTask::create([
        'project_id' => $project->id,
        'task_name' => 'Old Task Name',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(6)->toDateString(),
        'description' => 'Old description',
        'created_by' => $manager->id,
    ]);

    $newStart = now()->addDays(2)->format('Y-m-d');
    $newEnd = now()->addDays(8)->format('Y-m-d');

    $this->actingAs($manager)
        ->put(route('projects.tasks.update', [$project, $task]), [
            'task_name' => 'Updated Major Task Name',
            'start_date' => $newStart,
            'end_date' => $newEnd,
            'description' => 'Updated scope description.',
        ])
        ->assertRedirect(route('projects.show', $project));

    $task->refresh();
    expect($task->task_name)->toBe('Updated Major Task Name')
        ->and($task->start_date->format('Y-m-d'))->toBe($newStart)
        ->and($task->end_date->format('Y-m-d'))->toBe($newEnd)
        ->and($task->description)->toBe('Updated scope description.');
});

test('manager can delete a major task and its subtasks are cascade deleted', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create();
    $task = ProjectTask::create([
        'project_id' => $project->id,
        'task_name' => 'Obsolete Phase',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(6)->toDateString(),
        'created_by' => $manager->id,
    ]);

    $subtask = $task->subtasks()->create(['title' => 'Obsolete Subtask']);

    $this->actingAs($manager)
        ->delete(route('projects.tasks.destroy', [$project, $task]))
        ->assertRedirect(route('projects.show', $project));

    expect(ProjectTask::find($task->id))->toBeNull()
        ->and(ProjectSubtask::find($subtask->id))->toBeNull();
});

test('manager can add a sub task with a date to a major task', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create();
    $task = ProjectTask::create([
        'project_id' => $project->id,
        'task_name' => 'Electrical Wiring Phase',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(6)->toDateString(),
        'created_by' => $manager->id,
    ]);

    $dueDate = now()->addDays(3)->format('Y-m-d');

    $this->actingAs($manager)
        ->post(route('projects.tasks.subtasks.store', [$project, $task]), [
            'title' => 'Drop traveling cable into shaft',
            'due_date' => $dueDate,
        ])
        ->assertRedirect(route('projects.show', $project));

    $createdSubtask = $task->fresh()->subtasks->first();
    expect($createdSubtask)->not->toBeNull()
        ->and($createdSubtask->title)->toBe('Drop traveling cable into shaft')
        ->and($createdSubtask->due_date?->format('Y-m-d'))->toBe($dueDate);
});

test('ticking and unticking a sub task updates completion status and major task progress', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create();
    $task = ProjectTask::create([
        'project_id' => $project->id,
        'task_name' => 'Testing & Commissioning',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(6)->toDateString(),
        'created_by' => $manager->id,
    ]);

    $subtask1 = $task->subtasks()->create(['title' => 'Buffer drop test', 'is_completed' => false]);
    $subtask2 = $task->subtasks()->create(['title' => 'Governor trip test', 'is_completed' => false]);

    expect($task->fresh()->progress_percentage)->toBe(0);

    // Tick subtask 1
    $this->actingAs($manager)
        ->patchJson(route('projects.tasks.subtasks.toggle', [$project, $task, $subtask1]))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'is_completed' => true,
            'progress' => 50,
            'status' => 'in_progress',
        ]);

    expect($subtask1->fresh()->is_completed)->toBeTrue()
        ->and($subtask1->fresh()->completed_at)->not->toBeNull()
        ->and($task->fresh()->progress_percentage)->toBe(50);

    // Tick subtask 2 -> 100% complete
    $this->actingAs($manager)
        ->patchJson(route('projects.tasks.subtasks.toggle', [$project, $task, $subtask2]))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'is_completed' => true,
            'progress' => 100,
            'status' => 'completed',
        ]);

    expect($task->fresh()->progress_percentage)->toBe(100);

    // Untick subtask 1 -> reverts back to 50%
    $this->actingAs($manager)
        ->patchJson(route('projects.tasks.subtasks.toggle', [$project, $task, $subtask1]))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'is_completed' => false,
            'progress' => 50,
        ]);

    expect($subtask1->fresh()->is_completed)->toBeFalse()
        ->and($subtask1->fresh()->completed_at)->toBeNull();
});

test('manager can update and delete a sub task including its date', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create();
    $task = ProjectTask::create([
        'project_id' => $project->id,
        'task_name' => 'Door Assembly',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(6)->toDateString(),
        'created_by' => $manager->id,
    ]);

    $subtask = $task->subtasks()->create(['title' => 'Initial Title']);
    $newDate = now()->addDays(4)->format('Y-m-d');

    // Update with title and date
    $this->actingAs($manager)
        ->put(route('projects.tasks.subtasks.update', [$project, $task, $subtask]), [
            'title' => 'Renamed Landing Door Alignment',
            'due_date' => $newDate,
        ])
        ->assertRedirect(route('projects.show', $project));

    expect($subtask->fresh()->title)->toBe('Renamed Landing Door Alignment')
        ->and($subtask->fresh()->due_date?->format('Y-m-d'))->toBe($newDate);

    // Delete
    $this->actingAs($manager)
        ->delete(route('projects.tasks.subtasks.destroy', [$project, $task, $subtask]))
        ->assertRedirect(route('projects.show', $project));

    expect(ProjectSubtask::find($subtask->id))->toBeNull();
});

test('staff without manager or admin role cannot create or modify major tasks', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Staff');

    $project = Project::factory()->create();
    $task = ProjectTask::create([
        'project_id' => $project->id,
        'task_name' => 'Protected Task',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(6)->toDateString(),
    ]);

    $this->actingAs($staff)
        ->post(route('projects.tasks.store', $project), [
            'task_name' => 'Unauthorized Task',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
        ])
        ->assertForbidden();

    $this->actingAs($staff)
        ->put(route('projects.tasks.update', [$project, $task]), [
            'task_name' => 'Hacked Task',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(6)->toDateString(),
        ])
        ->assertForbidden();

    $this->actingAs($staff)
        ->delete(route('projects.tasks.destroy', [$project, $task]))
        ->assertForbidden();
});

test('project show page renders dynamic major tasks and sub tasks checklist without placeholder scaffolding or big progress bar', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create(['name' => 'Megamall Elevator Retrofit']);
    $task = ProjectTask::create([
        'project_id' => $project->id,
        'task_name' => 'Mobilization & Preliminary Safety Signoff',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(6)->toDateString(),
        'description' => 'Verify shaft opening clearances and mobilize crane.',
    ]);
    $subtask = $task->subtasks()->create([
        'title' => 'Check pit depth and buffer space',
        'due_date' => now()->addDays(2)->toDateString(),
        'is_completed' => false,
    ]);

    $this->actingAs($manager)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Project Progress Timeline')
        ->assertSee('+ Add Major Task')
        ->assertSee('Mobilization & Preliminary Safety Signoff')
        ->assertSee('Check pit depth and buffer space')
        ->assertSee($subtask->due_date->format('M j, Y'))
        ->assertDontSee('Mother Task Progress')
        ->assertDontSee('Progress 1: Mobilization & Initial Site Preparation Key Deliverables');
});

test('project show page renders requested exact empty state copy when no tasks exist without default initialize button', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create();
    // Delete any tasks created if factory or lifecycle created any
    $project->tasks()->delete();

    $this->actingAs($manager)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('This project does not have any weekly major tasks configured yet. You can create your first major task with week-based schedules.')
        ->assertSee('+ Add First Major Task')
        ->assertDontSee('Initialize Default Permit Milestone')
        ->assertDontSee('This project does not have any weekly mother tasks configured yet');
});

test('adding a sub task rejects dates outside the major task week', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create();
    $task = ProjectTask::create([
        'project_id' => $project->id,
        'task_name' => 'Week 3 Mechanical',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-07',
        'created_by' => $manager->id,
    ]);

    // Test date before start_date
    $this->actingAs($manager)
        ->post(route('projects.tasks.subtasks.store', [$project, $task]), [
            'title' => 'Early subtask',
            'due_date' => '2026-09-30',
        ])
        ->assertSessionHasErrors('due_date');

    // Test date after end_date
    $this->actingAs($manager)
        ->post(route('projects.tasks.subtasks.store', [$project, $task]), [
            'title' => 'Late subtask',
            'due_date' => '2026-10-08',
        ])
        ->assertSessionHasErrors('due_date');

    // Valid date within week passes
    $this->actingAs($manager)
        ->post(route('projects.tasks.subtasks.store', [$project, $task]), [
            'title' => 'Valid subtask',
            'due_date' => '2026-10-04',
        ])
        ->assertRedirect(route('projects.show', $project));

    expect($task->fresh()->subtasks)->toHaveCount(1);
});

test('updating a sub task rejects dates outside the major task week', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create();
    $task = ProjectTask::create([
        'project_id' => $project->id,
        'task_name' => 'Week 3 Mechanical',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-07',
        'created_by' => $manager->id,
    ]);

    $subtask = $task->subtasks()->create([
        'title' => 'Valid subtask',
        'due_date' => '2026-10-02',
    ]);

    // Test date after end_date
    $this->actingAs($manager)
        ->put(route('projects.tasks.subtasks.update', [$project, $task, $subtask]), [
            'title' => 'Updated title',
            'due_date' => '2026-10-10',
        ])
        ->assertSessionHasErrors('due_date');

    // Test date before start_date
    $this->actingAs($manager)
        ->put(route('projects.tasks.subtasks.update', [$project, $task, $subtask]), [
            'title' => 'Updated title',
            'due_date' => '2026-09-25',
        ])
        ->assertSessionHasErrors('due_date');

    // Valid date inside week passes
    $this->actingAs($manager)
        ->put(route('projects.tasks.subtasks.update', [$project, $task, $subtask]), [
            'title' => 'Updated title',
            'due_date' => '2026-10-06',
        ])
        ->assertRedirect(route('projects.show', $project));

    expect($subtask->fresh()->due_date->format('Y-m-d'))->toBe('2026-10-06');
});

test('creating a major task with sub task dates outside week fails validation', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create();

    $this->actingAs($manager)
        ->post(route('projects.tasks.store', $project), [
            'task_name' => 'Week 1 Site Prep',
            'start_date' => '2026-10-01',
            'end_date' => '2026-10-07',
            'subtasks' => ['Subtask 1'],
            'subtask_dates' => ['2026-10-15'],
        ])
        ->assertSessionHasErrors('subtask_dates.0');
});

test('ticking subtasks returns real-time progress payload with overall project progress', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create();
    $task1 = ProjectTask::create([
        'project_id' => $project->id,
        'task_name' => 'Week 1 Phase',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-07',
        'created_by' => $manager->id,
    ]);
    $subtask1 = $task1->subtasks()->create(['title' => 'Sub 1', 'is_completed' => false]);
    $subtask2 = $task1->subtasks()->create(['title' => 'Sub 2', 'is_completed' => false]);

    $task2 = ProjectTask::create([
        'project_id' => $project->id,
        'task_name' => 'Week 2 Phase',
        'start_date' => '2026-10-08',
        'end_date' => '2026-10-14',
        'created_by' => $manager->id,
    ]);
    $subtask3 = $task2->subtasks()->create(['title' => 'Sub 3', 'is_completed' => false]);
    $subtask4 = $task2->subtasks()->create(['title' => 'Sub 4', 'is_completed' => false]);

    // Total subtasks = 4. When subtask1 is ticked, task1 progress = 50%, overall progress = 25% (1/4)
    $this->actingAs($manager)
        ->patchJson(route('projects.tasks.subtasks.toggle', [$project, $task1, $subtask1]))
        ->assertOk()
        ->assertJson([
            'success' => true,
            'is_completed' => true,
            'progress' => 50,
            'status' => 'in_progress',
            'completed_count' => 1,
            'total_count' => 2,
            'overall_progress' => 25,
            'total_subtasks_across_project' => 4,
            'completed_subtasks_across_project' => 1,
        ]);
});
