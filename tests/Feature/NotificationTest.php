<?php

use App\Models\Notification;
use App\Models\Project;
use App\Models\ProjectTask;
use App\Models\User;
use App\Services\ProjectService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('admin and manager accounts are notified when a new project is created', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $staff = User::factory()->create();
    $staff->assignRole('Staff');

    $this->actingAs($manager);

    $projectService = app(ProjectService::class);
    $project = $projectService->create([
        'name' => 'Solaris Tower Installation',
        'client_name' => 'Solaris Corp',
        'contract_price' => 2500000,
        'start_date' => now()->toDateString(),
        'target_completion_date' => now()->addMonths(6)->toDateString(),
    ]);

    // Admin should have received notification
    $adminNotification = Notification::where('user_id', $admin->id)
        ->where('type', 'new_project')
        ->first();

    expect($adminNotification)->not->toBeNull();
    expect($adminNotification->title)->toBe('New Project Created');
    expect($adminNotification->message)->toContain('Solaris Tower Installation');
    expect($adminNotification->data['project_id'])->toBe($project->id);

    // Manager should have received notification
    $managerNotification = Notification::where('user_id', $manager->id)
        ->where('type', 'new_project')
        ->first();

    expect($managerNotification)->not->toBeNull();
    expect($managerNotification->title)->toBe('New Project Created');

    // Staff users also receive the new project notification
    $staffNotification = Notification::where('user_id', $staff->id)->where('type', 'new_project')->first();
    expect($staffNotification)->not->toBeNull();
    expect($staffNotification->title)->toBe('New Project Created');
});

test('admin and manager accounts are notified when a new major task is logged on a project', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create([
        'name' => 'Metro Mall Elevator',
        'created_by' => $admin->id,
    ]);

    // Clear any initial creation notifications
    Notification::query()->delete();

    $this->actingAs($manager)
        ->post(route('projects.tasks.store', $project), [
            'task_name' => 'Hoistway Structural Framing',
            'start_date' => now()->toDateString(),
            'end_date' => now()->addDays(7)->toDateString(),
            'description' => 'Erection of guide rails and support brackets.',
        ]);

    $adminNotif = Notification::where('user_id', $admin->id)
        ->where('type', 'new_task')
        ->first();

    expect($adminNotif)->not->toBeNull();
    expect($adminNotif->title)->toBe('New Major Task Created');
    expect($adminNotif->message)->toContain('Hoistway Structural Framing');
    expect($adminNotif->message)->toContain('Metro Mall Elevator');

    $managerNotif = Notification::where('user_id', $manager->id)
        ->where('type', 'new_task')
        ->first();

    expect($managerNotif)->not->toBeNull();
    expect($managerNotif->title)->toBe('New Major Task Created');
});

test('admin and manager accounts are notified when a specific sub task is logged on a project', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create([
        'name' => 'Horizon Tower',
        'created_by' => $admin->id,
    ]);

    $task = ProjectTask::create([
        'project_id' => $project->id,
        'task_name' => 'Electrical Staging',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(14)->toDateString(),
        'status' => 'pending',
        'sort_order' => 1,
        'created_by' => $admin->id,
    ]);

    // Clear prior notifications
    Notification::query()->delete();

    $this->actingAs($manager)
        ->post(route('projects.tasks.subtasks.store', [$project, $task]), [
            'title' => 'Wire main motor disconnect switch',
            'due_date' => now()->addDays(3)->toDateString(),
        ]);

    $adminNotif = Notification::where('user_id', $admin->id)
        ->where('type', 'new_task')
        ->first();

    expect($adminNotif)->not->toBeNull();
    expect($adminNotif->title)->toBe('New Sub Task Added');
    expect($adminNotif->message)->toContain('Wire main motor disconnect switch');
    expect($adminNotif->message)->toContain('Electrical Staging');
    expect($adminNotif->message)->toContain('Horizon Tower');
});

test('admin and manager accounts are notified a day before warranty period expiration', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    // Project with warranty ending tomorrow
    $expiringProject = Project::factory()->create([
        'name' => 'Grand Central Elevator Project',
        'warranty_end_date' => now()->addDay()->toDateString(),
        'created_by' => $admin->id,
    ]);

    // Project with warranty ending in 5 days (should NOT notify)
    $safeProject = Project::factory()->create([
        'name' => 'City Center Crane Project',
        'warranty_end_date' => now()->addDays(5)->toDateString(),
        'created_by' => $admin->id,
    ]);

    // Project with warranty ending yesterday (should NOT notify)
    $pastProject = Project::factory()->create([
        'name' => 'Old Pier Hoist',
        'warranty_end_date' => now()->subDay()->toDateString(),
        'created_by' => $admin->id,
    ]);

    Artisan::call('notifications:check-warranty');

    // Admin should get notification for expiringProject only
    $adminNotifs = Notification::where('user_id', $admin->id)
        ->where('type', 'warranty_expiry')
        ->get();

    expect($adminNotifs)->toHaveCount(1);
    expect($adminNotifs->first()->title)->toBe('Warranty Expiring Tomorrow');
    expect($adminNotifs->first()->message)->toContain('Grand Central Elevator Project');
    expect($adminNotifs->first()->data['project_id'])->toBe($expiringProject->id);

    // Manager should also get notification
    $managerNotifs = Notification::where('user_id', $manager->id)
        ->where('type', 'warranty_expiry')
        ->get();

    expect($managerNotifs)->toHaveCount(1);
    expect($managerNotifs->first()->message)->toContain('Grand Central Elevator Project');

    // Idempotency: Running it a second time today does not duplicate notifications
    Artisan::call('notifications:check-warranty');
    expect(Notification::where('user_id', $admin->id)->where('type', 'warranty_expiry')->count())->toBe(1);
});

test('notifications can be marked as read', function () {
    $user = User::factory()->create();
    $user->assignRole('Manager');

    $notification = Notification::create([
        'user_id' => $user->id,
        'type' => 'new_project',
        'title' => 'Project Created',
        'message' => 'Details about project',
    ]);

    expect($notification->isRead())->toBeFalse();

    $this->actingAs($user)
        ->post(route('notifications.mark-read', $notification))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($notification->fresh()->isRead())->toBeTrue();
    expect($notification->fresh()->read_at)->not->toBeNull();
});

test('all unread notifications can be marked as read at once', function () {
    $user = User::factory()->create();
    $user->assignRole('Manager');

    Notification::create([
        'user_id' => $user->id,
        'type' => 'new_project',
        'title' => 'Project 1',
        'message' => 'Details 1',
    ]);

    Notification::create([
        'user_id' => $user->id,
        'type' => 'new_task',
        'title' => 'Task 1',
        'message' => 'Details 2',
    ]);

    $this->actingAs($user)
        ->post(route('notifications.mark-all-read'))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(Notification::where('user_id', $user->id)->unread()->count())->toBe(0);
});

test('notifications can be archived', function () {
    $user = User::factory()->create();
    $user->assignRole('Manager');

    $notification = Notification::create([
        'user_id' => $user->id,
        'type' => 'new_project',
        'title' => 'Project to Archive',
        'message' => 'Details',
    ]);

    expect($notification->isArchived())->toBeFalse();

    $this->actingAs($user)
        ->post(route('notifications.archive', $notification))
        ->assertOk()
        ->assertJson(['success' => true]);

    $fresh = $notification->fresh();
    expect($fresh->isArchived())->toBeTrue();
    expect($fresh->archived_at)->not->toBeNull();

    // Archived notifications do not show up in the active list API
    $response = $this->actingAs($user)->get(route('notifications.index'));
    $response->assertOk();
    $notifications = $response->json('notifications');
    expect($notifications)->toBeEmpty();

    // But they show up when filtered by archived
    $archivedResponse = $this->actingAs($user)->get(route('notifications.index', ['filter' => 'archived']));
    $archivedResponse->assertOk();
    expect($archivedResponse->json('notifications'))->toHaveCount(1);
    expect($archivedResponse->json('notifications.0.id'))->toBe($notification->id);

    // Can be unarchived / restored
    $this->actingAs($user)
        ->post(route('notifications.unarchive', $notification))
        ->assertOk()
        ->assertJson(['success' => true]);

    expect($notification->fresh()->isArchived())->toBeFalse();

    // Now appears back in active list
    $activeResponse = $this->actingAs($user)->get(route('notifications.index'));
    expect($activeResponse->json('notifications'))->toHaveCount(1);
});

test('user cannot mark or archive another users notification', function () {
    $user1 = User::factory()->create();
    $user1->assignRole('Manager');

    $user2 = User::factory()->create();
    $user2->assignRole('Manager');

    $notification = Notification::create([
        'user_id' => $user1->id,
        'type' => 'new_project',
        'title' => 'Private Notif',
        'message' => 'Secret',
    ]);

    $this->actingAs($user2)
        ->post(route('notifications.mark-read', $notification))
        ->assertForbidden();

    $this->actingAs($user2)
        ->post(route('notifications.archive', $notification))
        ->assertForbidden();

    expect($notification->fresh()->isRead())->toBeFalse();
    expect($notification->fresh()->isArchived())->toBeFalse();
});

test('admin and manager receive notifications when project status changes to in-progress, completed, or archived', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create([
        'name' => 'Apex Tower Elevator',
        'status' => 'pending',
        'created_by' => $admin->id,
    ]);

    Notification::query()->delete();

    // 1. Transition to ongoing (In-Progress)
    $this->actingAs($manager)
        ->patch(route('projects.transition', $project), ['status' => 'ongoing'])
        ->assertRedirect();

    expect(Notification::where('user_id', $admin->id)->where('type', 'project_status_changed')->exists())->toBeTrue();
    expect(Notification::where('user_id', $manager->id)->where('type', 'project_status_changed')->exists())->toBeTrue();

    Notification::query()->delete();

    // 2. Transition to completed
    $this->actingAs($manager)
        ->patch(route('projects.transition', $project->fresh()), ['status' => 'completed'])
        ->assertRedirect();

    $completedNotif = Notification::where('user_id', $admin->id)->where('type', 'project_status_changed')->first();
    expect($completedNotif)->not->toBeNull();
    expect($completedNotif->title)->toBe('Project Status: Completed');

    Notification::query()->delete();

    // 3. Archive project
    $this->actingAs($manager)
        ->post(route('projects.archive', $project->fresh()))
        ->assertRedirect();

    expect(Notification::where('user_id', $admin->id)->where('type', 'project_archived')->exists())->toBeTrue();
    expect(Notification::where('user_id', $manager->id)->where('type', 'project_archived')->exists())->toBeTrue();
});
