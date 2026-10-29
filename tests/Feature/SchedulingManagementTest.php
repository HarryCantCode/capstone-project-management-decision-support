<?php

use App\Models\Personnel;
use App\Models\Project;
use App\Models\ProjectPersonnelHistory;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('manpower index page displays manage roster button and configure info bulk action', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $personnel = Personnel::create([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'date_of_birth' => '1990-01-01',
        'address' => '123 Rizal Street, Manila',
        'expertise' => 'Site Engineer',
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->get(route('scheduling.index'));

    $response->assertOk()
        ->assertSee('Manage Roster')
        ->assertSee(route('scheduling.roster'))
        ->assertSee('Configure Info')
        ->assertSee('Juan Dela Cruz');
});

test('staff cannot access manpower index or manage roster page', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Staff');

    $this->actingAs($staff)
        ->get(route('scheduling.index'))
        ->assertForbidden();

    $this->actingAs($staff)
        ->get(route('scheduling.roster'))
        ->assertForbidden();
});

test('manager and admin can access roster page with active and inactive personnel tabs', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $active = Personnel::create([
        'first_name' => 'Active',
        'last_name' => 'Worker',
        'date_of_birth' => '1992-05-10',
        'address' => '456 Quezon Blvd, QC',
        'expertise' => 'Worker',
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    $archived = Personnel::create([
        'first_name' => 'Archived',
        'last_name' => 'Engineer',
        'date_of_birth' => '1988-11-20',
        'address' => '789 Ortigas Ave, Pasig',
        'expertise' => 'Site Engineer',
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);
    $archived->delete(); // Soft-deleted (archived)

    // All tab
    $response = $this->actingAs($manager)->get(route('scheduling.roster', ['tab' => 'all']));
    $response->assertOk()
        ->assertSee('Active Worker')
        ->assertSee('Archived Engineer');

    // Active tab
    $responseActive = $this->actingAs($manager)->get(route('scheduling.roster', ['tab' => 'active']));
    $responseActive->assertOk()
        ->assertSee('Active Worker')
        ->assertDontSee('Archived Engineer');

    // Archived tab
    $responseArchived = $this->actingAs($manager)->get(route('scheduling.roster', ['tab' => 'archived']));
    $responseArchived->assertOk()
        ->assertSee('Archived Engineer')
        ->assertDontSee('Active Worker');
});

test('manager can update personnel information and assign to a project', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create(['status' => 'ongoing']);

    $personnel = Personnel::create([
        'first_name' => 'Carlos',
        'last_name' => 'Mendoza',
        'date_of_birth' => '1995-03-15',
        'address' => 'Old Address, Taguig',
        'expertise' => 'Worker',
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    $response = $this->actingAs($manager)->put(route('scheduling.update', $personnel), [
        'first_name' => 'Carlos Updated',
        'middle_name' => 'Santos',
        'last_name' => 'Mendoza',
        'date_of_birth' => '1995-03-15',
        'address' => 'New Validated Address, Taguig City',
        'expertise' => 'Foreman',
        'project_id' => $project->id,
    ]);

    $response->assertRedirect();

    $personnel->refresh();
    expect($personnel->first_name)->toBe('Carlos Updated')
        ->and($personnel->middle_name)->toBe('Santos')
        ->and($personnel->address)->toBe('New Validated Address, Taguig City')
        ->and($personnel->expertise)->toBe('Foreman')
        ->and($personnel->project_id)->toBe($project->id)
        ->and($personnel->status)->toBe('Assigned');

    expect(ProjectPersonnelHistory::where('personnel_id', $personnel->id)->where('project_id', $project->id)->exists())->toBeTrue();
});

test('manager can place personnel profile to archive via direct delete', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create(['status' => 'ongoing']);

    $personnel = Personnel::create([
        'first_name' => 'Mateo',
        'last_name' => 'Reyes',
        'date_of_birth' => '1985-07-22',
        'address' => 'Bonifacio High Street, Taguig',
        'expertise' => 'Safety Officer',
        'project_id' => $project->id,
        'date_assigned' => now()->toDateString(),
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    ProjectPersonnelHistory::create([
        'project_id' => $project->id,
        'personnel_id' => $personnel->id,
        'assigned_at' => now()->toDateString(),
        'assigned_by' => $manager->id,
    ]);

    $response = $this->actingAs($manager)->delete(route('scheduling.destroy', $personnel));
    $response->assertRedirect();

    $personnel->refresh();
    expect($personnel->trashed())->toBeTrue()
        ->and($personnel->project_id)->toBeNull()
        ->and($personnel->date_assigned)->toBeNull();

    // Project history should be closed with reason 'archived'
    $history = ProjectPersonnelHistory::where('personnel_id', $personnel->id)
        ->where('project_id', $project->id)
        ->first();
    expect($history->released_at)->not->toBeNull()
        ->and($history->release_reason)->toBe('archived');

    // Trashed worker is hidden from active manpower index table
    $indexResponse = $this->actingAs($manager)->get(route('scheduling.index'));
    $indexResponse->assertDontSee("personnel-row-{$personnel->id}");

    // Appears on roster under archived
    $rosterResponse = $this->actingAs($manager)->get(route('scheduling.roster', ['tab' => 'archived']));
    $rosterResponse->assertSee("roster-row-{$personnel->id}")
        ->assertSee('Mateo Reyes');

});

test('manager can place personnel profile to archive using archive_profile checkbox during update', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $personnel = Personnel::create([
        'first_name' => 'Alfonso',
        'last_name' => 'Bautista',
        'date_of_birth' => '1991-04-18',
        'address' => 'Makati Avenue',
        'expertise' => 'Worker / Installation Personnel',
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    $response = $this->actingAs($manager)->put(route('scheduling.update', $personnel), [
        'first_name' => 'Alfonso',
        'last_name' => 'Bautista',
        'date_of_birth' => '1991-04-18',
        'address' => 'Makati Avenue',
        'expertise' => 'Worker / Installation Personnel',
        'archive_profile' => '1',
    ]);

    $response->assertRedirect();

    $personnel->refresh();
    expect($personnel->trashed())->toBeTrue();
});

test('admin can restore an archived personnel back to active roster', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $personnel = Personnel::create([
        'first_name' => 'Roberto',
        'last_name' => 'Gomez',
        'date_of_birth' => '1987-12-05',
        'address' => 'Alabang, Muntinlupa',
        'expertise' => 'Foreman',
        'created_by' => $admin->id,
        'updated_by' => $admin->id,
    ]);
    $personnel->delete();

    expect($personnel->trashed())->toBeTrue();

    $response = $this->actingAs($admin)->post(route('scheduling.restore', $personnel->id));
    $response->assertRedirect();

    $personnel->refresh();
    expect($personnel->trashed())->toBeFalse()
        ->and($personnel->status)->toBe('Not Assigned');

    // Now appears again on active manpower index
    $indexResponse = $this->actingAs($admin)->get(route('scheduling.index'));
    $indexResponse->assertSee('Roberto Gomez');
});

test('staff cannot update, archive, or restore personnel', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Staff');

    $personnel = Personnel::create([
        'first_name' => 'Protected',
        'last_name' => 'Worker',
        'date_of_birth' => '1994-08-12',
        'address' => 'Sample Address',
        'expertise' => 'Worker / Installation Personnel',
    ]);

    $this->actingAs($staff)
        ->put(route('scheduling.update', $personnel), [
            'first_name' => 'Hacked',
            'last_name' => 'Worker',
            'date_of_birth' => '1994-08-12',
            'address' => 'Sample Address',
            'expertise' => 'Worker / Installation Personnel',
        ])
        ->assertForbidden();

    $this->actingAs($staff)
        ->delete(route('scheduling.destroy', $personnel))
        ->assertForbidden();

    $personnel->delete();

    $this->actingAs($staff)
        ->post(route('scheduling.restore', $personnel->id))
        ->assertForbidden();
});

test('personnel expertise options accept technician, project manager, qa inspector, elevator control, and worker / installation personnel', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $allExpertise = [
        'Site Engineer',
        'Foreman',
        'Safety Officer',
        'Technician',
        'Project Manager',
        'QA Inspector',
        'Elevator Control',
        'Worker / Installation Personnel',
    ];

    foreach ($allExpertise as $expertise) {
        $response = $this->actingAs($admin)->post(route('scheduling.store'), [
            'first_name'    => 'Test',
            'last_name'     => 'Expertise',
            'date_of_birth' => '1990-01-01',
            'address'       => 'Test Address',
            'expertise'     => $expertise,
        ]);

        $response->assertSessionHasNoErrors();
        $this->assertDatabaseHas('personnel', [
            'expertise' => $expertise,
        ]);
    }

    // Assert that deprecated 'Worker' fails validation
    $invalidResponse = $this->actingAs($admin)->post(route('scheduling.store'), [
        'first_name'    => 'Old',
        'last_name'     => 'Worker',
        'date_of_birth' => '1990-01-01',
        'address'       => 'Test Address',
        'expertise'     => 'Worker',
    ]);

    $invalidResponse->assertSessionHasErrors(['expertise']);
});
