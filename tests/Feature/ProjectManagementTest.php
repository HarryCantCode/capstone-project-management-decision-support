<?php

use App\Models\Project;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ─── Status transition tests — per OQ-3 ──────────────────────────────────────

test('pending project can transition to ongoing', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $project = Project::factory()->create(['status' => 'pending']);

    $this->actingAs($admin)
        ->patch(route('projects.transition', $project), ['status' => 'ongoing'])
        ->assertRedirect(route('projects.show', $project));

    expect($project->fresh()->status)->toBe('ongoing');
});

test('ongoing project can transition to completed', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create(['status' => 'ongoing']);

    $this->actingAs($manager)
        ->patch(route('projects.transition', $project), ['status' => 'completed'])
        ->assertRedirect(route('projects.show', $project));

    expect($project->fresh()->status)->toBe('completed');
});

test('pending project cannot skip to completed', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $project = Project::factory()->create(['status' => 'pending']);

    $this->actingAs($admin)
        ->patch(route('projects.transition', $project), ['status' => 'completed'])
        ->assertSessionHasErrors('status');

    expect($project->fresh()->status)->toBe('pending');
});

test('ongoing project cannot roll back to pending', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $project = Project::factory()->create(['status' => 'ongoing']);

    $this->actingAs($admin)
        ->patch(route('projects.transition', $project), ['status' => 'pending'])
        ->assertSessionHasErrors('status');

    expect($project->fresh()->status)->toBe('ongoing');
});

test('completed project cannot transition to any status', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $project = Project::factory()->create(['status' => 'completed']);

    foreach (['pending', 'ongoing'] as $targetStatus) {
        $this->actingAs($admin)
            ->patch(route('projects.transition', $project), ['status' => $targetStatus])
            ->assertSessionHasErrors('status');
    }

    expect($project->fresh()->status)->toBe('completed');
});

// ─── Status history tests ────────────────────────────────────────────────────

test('status history is recorded on project creation', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $project = Project::factory()->create(['status' => 'pending']);

    expect($project->statusHistory)->toHaveCount(1)
        ->and($project->statusHistory->first()->from_status)->toBeNull()
        ->and($project->statusHistory->first()->to_status)->toBe('pending');
});

test('status history is recorded on every status transition', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $project = Project::factory()->create(['status' => 'pending']);

    // Transition 1: pending → ongoing
    $this->actingAs($admin)
        ->patch(route('projects.transition', $project), ['status' => 'ongoing']);

    // Transition 2: ongoing → completed
    $this->actingAs($admin)
        ->patch(route('projects.transition', $project->fresh()), ['status' => 'completed']);

    $history = $project->fresh()->statusHistory;

    // 1 creation record + 2 transitions = 3 rows
    expect($history)->toHaveCount(3);
    expect($history->get(1)->from_status)->toBe('pending');
    expect($history->get(1)->to_status)->toBe('ongoing');
    expect($history->get(2)->from_status)->toBe('ongoing');
    expect($history->get(2)->to_status)->toBe('completed');
});

// ─── Authorization tests — role access ───────────────────────────────────────

test('inventory staff cannot view project list', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Inventory Staff');

    $this->actingAs($staff)
        ->get(route('projects.index'))
        ->assertForbidden();
});

test('inventory staff cannot create a project', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Inventory Staff');

    $this->actingAs($staff)
        ->post(route('projects.store'), [
            'name' => 'Test Project',
            'client_name' => 'Test Client',
        ])
        ->assertForbidden();
});

test('inventory staff cannot delete a project', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Inventory Staff');

    $project = Project::factory()->create();

    $this->actingAs($staff)
        ->delete(route('projects.destroy', $project))
        ->assertForbidden();
});

test('manager cannot delete a project', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create();

    $this->actingAs($manager)
        ->delete(route('projects.destroy', $project))
        ->assertForbidden();
});

test('admin can delete a project', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $project = Project::factory()->create();

    $this->actingAs($admin)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'));

    expect(Project::find($project->id))->toBeNull(); // soft deleted
    expect(Project::withTrashed()->find($project->id))->not->toBeNull(); // still in DB
});
