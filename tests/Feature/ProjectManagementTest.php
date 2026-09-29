<?php

use App\Models\Personnel;
use App\Models\Project;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('manager can create a project with contract price, site address, project type, warranty, and payment status', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $targetDate = now()->addMonths(2)->format('Y-m-d');
    $startDate = now()->format('Y-m-d');

    $this->actingAs($manager)
        ->post(route('projects.store'), [
            'name' => 'Makati Office Lift Modernization',
            'client_name' => 'Northpoint Properties',
            'address' => '6789 Ayala Avenue',
            'barangay' => 'Bel-Air',
            'city' => 'Makati City',
            'project_type' => 'Commercial',
            'payment_status' => 'Paid',
            'warranty_period' => '1 Year',
            'description' => 'Modernize the passenger lift controls and doors.',
            'start_date' => $startDate,
            'target_completion_date' => $targetDate,
            'contract_price' => 1250000.00,
        ])
        ->assertRedirect();

    $project = Project::firstOrFail();

    expect($project->status)->toBe('pending')
        ->and($project->project_code)->toMatch('/^DEX-\d{4}-\d{3}$/')
        ->and($project->created_by)->toBe($manager->id)
        ->and($project->address)->toBe('6789 Ayala Avenue')
        ->and($project->barangay)->toBe('Bel-Air')
        ->and($project->city)->toBe('Makati City')
        ->and($project->project_type)->toBe('Commercial')
        ->and($project->payment_status)->toBe('Paid')
        ->and($project->warranty_period)->toBe('1 Year')
        ->and($project->warranty_end_date->format('Y-m-d'))->toBe(now()->addYear()->format('Y-m-d'))
        ->and($project->full_address)->toBe('6789 Ayala Avenue, Bel-Air, Makati City')
        ->and((float) $project->contract_price)->toBe(1250000.00)
        ->and((float) $project->actual_spend)->toBe(0.00)
        ->and((float) $project->remaining_budget)->toBe(1250000.00)
        ->and($project->target_completion_date->format('Y-m-d'))->toBe($targetDate)
        ->and($project->statusHistory)->toHaveCount(1);
});

test('manager can update project details and audit trail records changes', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $project = Project::factory()->create(['status' => 'pending', 'contract_price' => 500000.00]);

    $this->actingAs($manager)
        ->put(route('projects.update', $project), [
            'name' => 'Updated Lift Modernization',
            'client_name' => 'Northpoint Properties',
            'address' => '123 New Address St',
            'barangay' => 'San Antonio',
            'city' => 'Pasig City',
            'project_type' => 'Industrial',
            'payment_status' => 'Partial',
            'description' => 'Updated project scope.',
            'start_date' => now()->format('Y-m-d'),
            'target_completion_date' => now()->addMonths(3)->format('Y-m-d'),
            'contract_price' => 750000.00,
            'status' => 'completed',
        ])
        ->assertRedirect(route('projects.show', $project));

    expect($project->fresh()->name)->toBe('Updated Lift Modernization')
        ->and($project->fresh()->status)->toBe('pending')
        ->and($project->fresh()->address)->toBe('123 New Address St')
        ->and($project->fresh()->project_type)->toBe('Industrial')
        ->and($project->fresh()->payment_status)->toBe('Partial')
        ->and((float) $project->fresh()->contract_price)->toBe(750000.00);

    // Verify trailing info is recorded (statusHistory is ordered DESC, so first() is latest)
    $latestHistory = $project->fresh()->statusHistory->first();
    expect($latestHistory->notes)->toContain('Project name changed')
        ->and($latestHistory->notes)->toContain('Contract price updated');
});

test('creating a project rejects past start dates', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $pastDate = now()->subDays(2)->format('Y-m-d');
    $targetDate = now()->addMonths(2)->format('Y-m-d');

    $this->actingAs($manager)
        ->post(route('projects.store'), [
            'name' => 'Past Date Project',
            'client_name' => 'Acme Corp',
            'address' => '123 Past Way',
            'barangay' => 'Barangay 1',
            'city' => 'Taguig City',
            'project_type' => 'Residential',
            'payment_status' => 'Partial',
            'start_date' => $pastDate,
            'target_completion_date' => $targetDate,
            'contract_price' => 500000.00,
        ])
        ->assertSessionHasErrors(['start_date']);
});

test('creating a project requires site address, project type, and payment status', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->post(route('projects.store'), [
            'name' => 'Incomplete Project',
            'client_name' => 'Acme Corp',
            'start_date' => now()->format('Y-m-d'),
            'target_completion_date' => now()->addMonth()->format('Y-m-d'),
            'contract_price' => 100000.00,
        ])
        ->assertSessionHasErrors(['address', 'barangay', 'city', 'project_type', 'payment_status']);
});

test('manager can render project create and edit pages with new fields', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create([
        'address' => '456 Pioneer St',
        'barangay' => 'Barangka',
        'city' => 'Mandaluyong City',
        'project_type' => 'Commercial',
        'payment_status' => 'Paid',
    ]);

    $this->actingAs($manager)
        ->get(route('projects.create'))
        ->assertOk()
        ->assertSee('Site Address / Project Location')
        ->assertSee('Project Type')
        ->assertSee('Payment Status')
        ->assertSee('Warranty Period');

    $this->actingAs($manager)
        ->get(route('projects.edit', $project))
        ->assertOk()
        ->assertSee('Site Address / Project Location')
        ->assertSee('456 Pioneer St')
        ->assertSee('Barangka')
        ->assertSee('Mandaluyong City')
        ->assertSee('Commercial')
        ->assertSee('Paid');
});

test('manager can add and delete itemized project costs with automatic spend recalculation', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $project = Project::factory()->create([
        'contract_price' => 1000000.00,
        'actual_spend' => 0.00,
    ]);

    // 1. Add first cost item
    $this->actingAs($manager)
        ->post(route('projects.costs.store', $project), [
            'description' => 'Elevator steel brackets and guide rails',
            'cost_type' => 'materials',
            'amount' => 250000.00,
            'incurred_date' => now()->format('Y-m-d'),
        ])
        ->assertRedirect(route('projects.show', $project));

    expect($project->fresh()->costs)->toHaveCount(1)
        ->and((float) $project->fresh()->actual_spend)->toBe(250000.00)
        ->and((float) $project->fresh()->remaining_budget)->toBe(750000.00);

    // 2. Add second cost item
    $this->actingAs($manager)
        ->post(route('projects.costs.store', $project), [
            'description' => 'Hoist crane rental for motor placement',
            'cost_type' => 'equipment',
            'amount' => 100000.00,
            'incurred_date' => now()->format('Y-m-d'),
        ])
        ->assertRedirect(route('projects.show', $project));

    expect($project->fresh()->costs)->toHaveCount(2)
        ->and((float) $project->fresh()->actual_spend)->toBe(350000.00)
        ->and((float) $project->fresh()->remaining_budget)->toBe(650000.00)
        ->and($project->fresh()->spend_percentage)->toBe(35.0);

    // 3. Delete cost item
    $cost = $project->fresh()->costs->first();
    $this->actingAs($manager)
        ->delete(route('projects.costs.destroy', ['project' => $project, 'cost' => $cost]))
        ->assertRedirect(route('projects.show', $project));

    expect($project->fresh()->costs)->toHaveCount(1)
        ->and((float) $project->fresh()->actual_spend)->toBe(100000.00)
        ->and((float) $project->fresh()->remaining_budget)->toBe(900000.00);
});

test('manager can assign and unassign personnel on project view', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $project = Project::factory()->create();

    $personnel = Personnel::create([
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'date_of_birth' => '1990-05-15',
        'address' => 'Makati City',
        'expertise' => 'Site Engineer',
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    // 1. Assign to project
    $this->actingAs($manager)
        ->post(route('projects.assign-personnel', $project), [
            'personnel_id' => $personnel->id,
        ])
        ->assertRedirect(route('projects.show', $project));

    expect($personnel->fresh()->project_id)->toBe($project->id)
        ->and($personnel->fresh()->status)->toBe('Assigned');

    // 2. Unassign from project
    $this->actingAs($manager)
        ->post(route('projects.unassign-personnel', ['project' => $project, 'personnel' => $personnel]))
        ->assertRedirect(route('projects.show', $project));

    expect($personnel->fresh()->project_id)->toBeNull()
        ->and($personnel->fresh()->status)->toBe('Not Assigned');
});

// ─── Status transition tests ──────────────────────────────────────────────────

test('project can transition between pending, ongoing, delayed, and completed', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $project = Project::factory()->create(['status' => 'pending']);

    // 1. pending → ongoing
    $this->actingAs($admin)
        ->patch(route('projects.transition', $project), ['status' => 'ongoing'])
        ->assertRedirect(route('projects.show', $project));
    expect($project->fresh()->status)->toBe('ongoing');

    // 2. ongoing → delayed
    $this->actingAs($admin)
        ->patch(route('projects.transition', $project->fresh()), [
            'status' => 'delayed',
            'notes' => 'Parts shipment delayed by supplier.',
        ])
        ->assertRedirect(route('projects.show', $project));
    expect($project->fresh()->status)->toBe('delayed');

    // 3. delayed → completed
    $this->actingAs($admin)
        ->patch(route('projects.transition', $project->fresh()), [
            'status' => 'completed',
            'notes' => 'Project finished and signed off by client.',
        ])
        ->assertRedirect(route('projects.show', $project));
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

test('status history is recorded on every status transition with time and user', function () {
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
    // Ordered newest to oldest:
    expect($history->get(0)->to_status)->toBe('completed')
        ->and($history->get(0)->from_status)->toBe('ongoing')
        ->and($history->get(0)->changed_by)->toBe($admin->id);
    expect($history->get(1)->to_status)->toBe('ongoing')
        ->and($history->get(1)->from_status)->toBe('pending');
    expect($history->get(2)->to_status)->toBe('pending')
        ->and($history->get(2)->from_status)->toBeNull();
});

// ─── Authorization tests — role access ───────────────────────────────────────

test('staff cannot view project list', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Staff');

    $this->actingAs($staff)
        ->get(route('projects.index'))
        ->assertForbidden();
});

test('staff cannot create a project', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Staff');

    $this->actingAs($staff)
        ->post(route('projects.store'), [
            'name' => 'Test Project',
            'client_name' => 'Test Client',
            'address' => '123 Test St',
            'barangay' => 'Test Barangay',
            'city' => 'Test City',
            'project_type' => 'Commercial',
            'payment_status' => 'Partial',
            'start_date' => now()->format('Y-m-d'),
            'target_completion_date' => now()->addMonth()->format('Y-m-d'),
            'contract_price' => 500000.00,
        ])
        ->assertForbidden();
});

test('staff cannot delete a project', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Staff');

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

test('deleting a project releases all assigned manpower and marks their status as Not Assigned', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $project = Project::factory()->create([
        'name' => 'Project To Be Deleted',
        'status' => 'ongoing',
    ]);

    $personnel1 = \App\Models\Personnel::create([
        'employee_id' => 'EMP-2026191',
        'first_name' => 'Mario',
        'last_name' => 'Bautista',
        'date_of_birth' => '1991-03-10',
        'address' => 'Pasig City',
        'expertise' => 'Site Engineer',
        'created_by' => $admin->id,
    ]);

    $personnel2 = \App\Models\Personnel::create([
        'employee_id' => 'EMP-2026192',
        'first_name' => 'Carlo',
        'last_name' => 'Mendoza',
        'date_of_birth' => '1993-07-22',
        'address' => 'Taguig City',
        'expertise' => 'Worker',
        'created_by' => $admin->id,
    ]);

    // 1. Assign personnel to project
    $this->actingAs($admin)->post(route('projects.assign-personnel', $project), [
        'personnel_id' => $personnel1->id,
    ]);
    $this->actingAs($admin)->post(route('projects.assign-personnel', $project), [
        'personnel_id' => $personnel2->id,
    ]);

    expect($personnel1->fresh()->project_id)->toBe($project->id);
    expect($personnel2->fresh()->project_id)->toBe($project->id);
    expect($personnel1->fresh()->status)->toBe('Assigned');
    expect($personnel2->fresh()->status)->toBe('Assigned');

    // 2. Delete the project
    $this->actingAs($admin)
        ->delete(route('projects.destroy', $project))
        ->assertRedirect(route('projects.index'));

    // 3. Verify personnel are automatically released
    $freshPersonnel1 = $personnel1->fresh();
    $freshPersonnel2 = $personnel2->fresh();

    expect($freshPersonnel1->project_id)->toBeNull();
    expect($freshPersonnel1->date_assigned)->toBeNull();
    expect($freshPersonnel1->status)->toBe('Not Assigned');

    expect($freshPersonnel2->project_id)->toBeNull();
    expect($freshPersonnel2->date_assigned)->toBeNull();
    expect($freshPersonnel2->status)->toBe('Not Assigned');

    // 4. Verify manpower history is closed with release_reason = project_deleted
    $history = \App\Models\ProjectPersonnelHistory::where('project_id', $project->id)->get();
    expect($history)->toHaveCount(2);
    expect($history->first()->release_reason)->toBe('project_deleted');
    expect($history->first()->released_at)->not->toBeNull();
    expect($history->last()->release_reason)->toBe('project_deleted');
    expect($history->last()->released_at)->not->toBeNull();

    // 5. Verify scheduling page shows both personnel as Not Assigned
    $schedulingResponse = $this->actingAs($admin)->get(route('scheduling.index'));
    $schedulingResponse->assertOk()
        ->assertSee('EMP-2026191')
        ->assertSee('EMP-2026192')
        ->assertSee('Not Assigned');
});

test('completing a project releases assigned personnel and retains archived manpower history', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $project = Project::factory()->create([
        'name' => 'Ayala Center Elevator Overhaul',
        'status' => 'ongoing',
    ]);

    $personnel1 = \App\Models\Personnel::create([
        'employee_id' => 'EMP-2026091',
        'first_name' => 'Juan',
        'last_name' => 'Dela Cruz',
        'date_of_birth' => '1990-05-15',
        'address' => 'Makati City',
        'expertise' => 'Site Engineer',
        'created_by' => $admin->id,
    ]);

    $personnel2 = \App\Models\Personnel::create([
        'employee_id' => 'EMP-2026092',
        'first_name' => 'Pedro',
        'last_name' => 'Santos',
        'date_of_birth' => '1992-08-20',
        'address' => 'Quezon City',
        'expertise' => 'Foreman',
        'created_by' => $admin->id,
    ]);

    // 1. Assign personnel to project
    $this->actingAs($admin)->post(route('projects.assign-personnel', $project), [
        'personnel_id' => $personnel1->id,
    ]);
    $this->actingAs($admin)->post(route('projects.assign-personnel', $project), [
        'personnel_id' => $personnel2->id,
    ]);

    expect($personnel1->fresh()->project_id)->toBe($project->id);
    expect($personnel2->fresh()->project_id)->toBe($project->id);
    expect($personnel1->fresh()->status)->toBe('Assigned');

    // 2. Mark project as completed
    $this->actingAs($admin)->patch(route('projects.transition', $project), [
        'status' => 'completed',
        'notes' => 'Construction completed and handed over.',
    ]);

    // 3. Verify personnel are released and available
    expect($personnel1->fresh()->project_id)->toBeNull();
    expect($personnel2->fresh()->project_id)->toBeNull();
    expect($personnel1->fresh()->status)->toBe('Not Assigned');
    expect($personnel2->fresh()->status)->toBe('Not Assigned');

    // 4. Verify history records exist and are marked project_completed
    $history = \App\Models\ProjectPersonnelHistory::where('project_id', $project->id)->get();
    expect($history)->toHaveCount(2);
    expect($history->first()->release_reason)->toBe('project_completed');
    expect($history->first()->released_at)->not->toBeNull();

    // 5. Verify the completed project show page displays the archived manpower
    $response = $this->actingAs($admin)->get(route('projects.show', $project));
    $response->assertOk()
        ->assertSee('Construction Team (Archived)')
        ->assertSee('Juan Dela Cruz')
        ->assertSee('Pedro Santos')
        ->assertSee('Site Engineer')
        ->assertSee('Foreman')
        ->assertSee('Project Completed');
});

test('manager and admin can bulk assign multiple personnel to a project from manpower management', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $project = Project::factory()->create(['name' => 'BGC Grand Tower', 'status' => 'ongoing']);

    $personnel1 = \App\Models\Personnel::create([
        'employee_id' => 'EMP-2026093',
        'first_name' => 'Alex',
        'last_name' => 'Gonzales',
        'date_of_birth' => '1995-01-10',
        'address' => 'Taguig City',
        'expertise' => 'Worker',
        'created_by' => $admin->id,
    ]);

    $personnel2 = \App\Models\Personnel::create([
        'employee_id' => 'EMP-2026094',
        'first_name' => 'Maria',
        'last_name' => 'Reyes',
        'date_of_birth' => '1993-04-18',
        'address' => 'Pasig City',
        'expertise' => 'Safety Officer',
        'created_by' => $admin->id,
    ]);

    // Bulk assign both workers to $project
    $this->actingAs($admin)->post(route('scheduling.bulk-reassign'), [
        'personnel_ids' => [$personnel1->id, $personnel2->id],
        'project_id'    => $project->id,
    ])->assertRedirect(route('scheduling.index'));

    expect($personnel1->fresh()->project_id)->toBe($project->id);
    expect($personnel2->fresh()->project_id)->toBe($project->id);

    // Bulk unassign both workers
    $this->actingAs($admin)->post(route('scheduling.bulk-reassign'), [
        'personnel_ids' => [$personnel1->id, $personnel2->id],
        'project_id'    => null,
    ])->assertRedirect(route('scheduling.index'));

    expect($personnel1->fresh()->project_id)->toBeNull();
    expect($personnel2->fresh()->project_id)->toBeNull();
});

test('manager and admin can assign multiple personnel simultaneously from project details modal', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $project = Project::factory()->create(['name' => 'Ortigas Center Hub', 'status' => 'ongoing']);

    $p1 = \App\Models\Personnel::create([
        'employee_id' => 'EMP-2026095',
        'first_name' => 'Carlos',
        'last_name' => 'Mendoza',
        'date_of_birth' => '1989-11-20',
        'address' => 'Mandaluyong City',
        'expertise' => 'Foreman',
        'created_by' => $admin->id,
    ]);

    $p2 = \App\Models\Personnel::create([
        'employee_id' => 'EMP-2026096',
        'first_name' => 'Elena',
        'last_name' => 'Bautista',
        'date_of_birth' => '1991-07-08',
        'address' => 'San Juan City',
        'expertise' => 'Site Engineer',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)->post(route('projects.assign-personnel', $project), [
        'personnel_ids' => [$p1->id, $p2->id],
    ])->assertRedirect(route('projects.show', $project));

    expect($p1->fresh()->project_id)->toBe($project->id);
    expect($p2->fresh()->project_id)->toBe($project->id);
    expect($project->fresh()->personnel)->toHaveCount(2);
});

test('updating project info and managing manpower creates detailed audit trail records', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $project = Project::factory()->create([
        'name' => 'Original Project Name',
        'client_name' => 'Original Client Corp',
        'contract_price' => 1000000.00,
        'status' => 'ongoing',
    ]);

    // 1. Update Project Information
    $this->actingAs($admin)->put(route('projects.update', $project), [
        'name' => 'Renamed Skyrise Tower',
        'client_name' => 'Megaworld Properties',
        'address' => '32nd Street',
        'barangay' => 'Fort Bonifacio',
        'city' => 'Taguig City',
        'project_type' => 'Commercial',
        'payment_status' => 'Paid',
        'contract_price' => 1500000.00,
        'description' => 'Updated high-rise project scope',
        'start_date' => '2026-09-01',
        'target_completion_date' => '2027-03-31',
    ])->assertRedirect(route('projects.show', $project));

    $histories = $project->fresh()->statusHistory;
    $latestUpdate = $histories->first();
    expect($latestUpdate->notes)
        ->toContain("Project name changed from 'Original Project Name' to 'Renamed Skyrise Tower'")
        ->toContain("Client changed from 'Original Client Corp' to 'Megaworld Properties'")
        ->toContain("Contract price updated from ₱1,000,000.00 to ₱1,500,000.00");

    // 2. Assign Manpower
    $worker = \App\Models\Personnel::create([
        'employee_id' => 'EMP-2026097',
        'first_name' => 'Ricardo',
        'last_name' => 'Dalisay',
        'date_of_birth' => '1988-03-12',
        'address' => 'Manila',
        'expertise' => 'Foreman',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)->post(route('projects.assign-personnel', $project), [
        'personnel_ids' => [$worker->id],
    ])->assertRedirect(route('projects.show', $project));

    $assignHistory = $project->fresh()->statusHistory->first();
    expect($assignHistory->notes)->toContain("Assigned manpower: EMP-2026097 (Ricardo Dalisay - Foreman)");

    // 3. Unassign Manpower
    $this->actingAs($admin)->post(route('projects.unassign-personnel', [
        'project' => $project,
        'personnel' => $worker,
    ]))->assertRedirect(route('projects.show', $project));

    $unassignHistory = $project->fresh()->statusHistory->first();
    expect($unassignHistory->notes)->toContain("Unassigned manpower: EMP-2026097 (Ricardo Dalisay - Foreman)");

    // 4. Verify Project Show page renders all audit trail notes
    $response = $this->actingAs($admin)->get(route('projects.show', $project));
    $response->assertOk()
        ->assertSee("Renamed Skyrise Tower")
        ->assertSee("Megaworld Properties")
        ->assertSee("Ricardo Dalisay");
});

test('projects index and show pages render successfully even when creator or updater is archived', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $creator = User::factory()->create();
    $project = Project::factory()->create([
        'name' => 'Seeded Archived Creator Project',
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
    ]);

    $creator->delete();

    $this->actingAs($admin)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertSee('Seeded Archived Creator Project');

    $this->actingAs($admin)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Seeded Archived Creator Project');
});

test('creator and audit trail names remain visible even after user account is archived (soft-deleted)', function () {
    $creator = User::factory()->create([
        'name' => 'Dominic Toretto',
        'first_name' => 'Dominic',
        'last_name' => 'Toretto',
    ]);
    $creator->assignRole('Manager');

    $project = Project::factory()->create([
        'name' => 'Fast Track Warehouse',
        'created_by' => $creator->id,
        'updated_by' => $creator->id,
        'status' => 'ongoing',
    ]);

    // Create a status update authored by $creator
    \App\Models\ProjectStatusHistory::create([
        'project_id' => $project->id,
        'from_status' => 'pending',
        'to_status' => 'ongoing',
        'notes' => 'Ground excavation started',
        'changed_by' => $creator->id,
        'changed_at' => now(),
    ]);

    // Archive (soft delete) the creator's account
    $creator->delete();
    expect($creator->fresh()->trashed())->toBeTrue();
    expect(User::find($creator->id))->toBeNull();
    expect(User::withTrashed()->find($creator->id))->not->toBeNull();

    // Verify relations load the archived user with withTrashed()
    $freshProject = Project::with(['creator', 'updater', 'statusHistory.changedBy'])->find($project->id);
    expect($freshProject->creator)->not->toBeNull();
    expect($freshProject->creator->name)->toBe('Dominic Toretto');
    expect($freshProject->statusHistory->first()->changedBy->name)->toBe('Dominic Toretto');

    // Verify UI views render the archived user's name properly
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route('projects.index'))
        ->assertOk()
        ->assertSee('Dominic Toretto');

    $this->actingAs($admin)
        ->get(route('projects.show', $project))
        ->assertOk()
        ->assertSee('Dominic Toretto')
        ->assertSee('Ground excavation started');
});
