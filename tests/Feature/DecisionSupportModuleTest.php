<?php

use App\Models\Personnel;
use App\Models\Project;
use App\Models\ProjectCost;
use App\Models\ProjectSubtask;
use App\Models\ProjectTask;
use App\Models\Resource;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;

beforeEach(function () {
    $this->seed(RolePermissionSeeder::class);
});

it('allows Admin and Manager to access the Decision Support module and blocks Staff with 403', function () {
    $admin = User::factory()->create(['two_factor_Confirmed_at' => now()]);
    $admin->assignRole('Admin');

    $manager = User::factory()->create(['two_factor_Confirmed_at' => now()]);
    $manager->assignRole('Manager');

    $staff = User::factory()->create(['two_factor_Confirmed_at' => now()]);
    $staff->assignRole('Staff');

    $this->actingAs($admin)
        ->withSession(['two_factor_verified' => true])
        ->get(route('decision-support.index'))
        ->assertOk()
        ->assertSee('Decision Support')
        ->assertSee('What-If Simulator');

    $this->actingAs($manager)
        ->withSession(['two_factor_verified' => true])
        ->get(route('decision-support.index'))
        ->assertOk();

    $this->actingAs($staff)
        ->withSession(['two_factor_verified' => true])
        ->get(route('decision-support.index'))
        ->assertForbidden();
});

it('evaluates project health matrix and rule-based health checks across budget, schedule, manpower, and inventory', function () {
    $admin = User::factory()->create(['two_factor_Confirmed_at' => now()]);
    $admin->assignRole('Admin');

    $project = Project::factory()->create([
        'name' => 'Tower A Elevator Installation',
        'status' => 'ongoing',
        'contract_price' => 1000000.00,
        'actual_spend' => 900000.00,
        'start_date' => now()->subDays(30),
        'target_completion_date' => now()->addDays(60),
        'created_by' => $admin->id,
    ]);

    // Low stock material used on project
    $material = Resource::create([
        'name' => 'Guide Rail Bracket',
        'resource_code' => 'MAT-DSS-001',
        'type' => 'Material',
        'model' => 'GRB-10',
        'quantity_available' => 2,
        'price' => 25000.00,
        'unit_cost' => 20000.00,
        'demand_status' => 'fast-moving',
        'condition' => 'Good',
        'created_by' => $admin->id,
    ]);

    ProjectCost::create([
        'project_id' => $project->id,
        'resource_id' => $material->id,
        'description' => 'Guide Rail Brackets batch',
        'cost_type' => 'materials',
        'amount' => 750000.00,
        'quantity' => 30,
        'incurred_date' => now()->subDays(5),
        'created_by' => $admin->id,
    ]);

    // Overdue task & subtask
    $task = ProjectTask::create([
        'project_id' => $project->id,
        'task_name' => 'Shaft Guide Rail Alignment',
        'start_date' => now()->subDays(15),
        'end_date' => now()->subDays(5),
        'status' => 'in_progress',
        'sort_order' => 1,
        'created_by' => $admin->id,
    ]);

    ProjectSubtask::create([
        'project_task_id' => $task->id,
        'title' => 'Plumb line laser verification',
        'due_date' => now()->subDays(5),
        'is_completed' => false,
        'sort_order' => 1,
    ]);

    // Available personnel for crew suggestion ranking
    Personnel::create([
        'employee_id' => 'EMP-2026901',
        'first_name' => 'Marco',
        'last_name' => 'Reyes',
        'date_of_birth' => '1990-04-12',
        'address' => 'Makati City',
        'expertise' => Personnel::EXPERTISE_PROJECT_MANAGER,
        'project_id' => null,
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)
        ->withSession(['two_factor_verified' => true])
        ->get(route('decision-support.index', ['project_id' => $project->id]));

    $response->assertOk()
        ->assertSee('Tower A Elevator Installation')
        ->assertSee('Guide Rail Bracket')
        ->assertSee('Marco Reyes');
});

it('runs in-memory what-if simulations without modifying database records', function () {
    $admin = User::factory()->create(['two_factor_Confirmed_at' => now()]);
    $admin->assignRole('Admin');

    $project = Project::factory()->create([
        'name' => 'Sim Target Project',
        'status' => 'ongoing',
        'contract_price' => 2000000.00,
        'actual_spend' => 1200000.00,
        'start_date' => now()->subDays(20),
        'target_completion_date' => now()->addDays(40),
        'created_by' => $admin->id,
    ]);

    $material = Resource::create([
        'name' => 'Hoist Steel Cable',
        'resource_code' => 'MAT-DSS-002',
        'type' => 'Material',
        'model' => 'HSC-50',
        'quantity_available' => 4,
        'price' => 50000.00,
        'unit_cost' => 40000.00,
        'demand_status' => 'fast-moving',
        'condition' => 'Good',
        'created_by' => $admin->id,
    ]);

    // 1. Feasibility Simulation
    $this->actingAs($admin)
        ->withSession(['two_factor_verified' => true])
        ->postJson(route('decision-support.simulate'), [
            'scenario_type' => 'feasibility',
            'contract_budget' => 3000000,
            'start_date' => now()->addDays(10)->toDateString(),
            'target_completion_date' => now()->addDays(100)->toDateString(),
            'required_roles' => [
                ['expertise' => Personnel::EXPERTISE_PROJECT_MANAGER, 'count' => 1],
            ],
            'required_materials' => [
                ['resource_id' => $material->id, 'quantity' => 10],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('scenario_type', 'feasibility')
        ->assertJsonPath('simulation.status', 'critical');

    // 2. Delay Propagation Simulation
    $this->actingAs($admin)
        ->withSession(['two_factor_verified' => true])
        ->postJson(route('decision-support.simulate'), [
            'scenario_type' => 'delay',
            'project_id' => $project->id,
            'slip_days' => 14,
        ])
        ->assertOk()
        ->assertJsonPath('scenario_type', 'delay')
        ->assertJsonPath('simulation.slip_days', 14);

    // 3. Supplier Procurement Trade-Off Simulation (Replaced Budget Adjustment)
    $this->actingAs($admin)
        ->withSession(['two_factor_verified' => true])
        ->postJson(route('decision-support.simulate'), [
            'scenario_type' => 'supplier',
            'project_id' => $project->id,
            'material_name' => 'Traction Machine Drive',
            'required_quantity' => 2,
            'suppliers' => [
                [
                    'name' => 'Supplier Alpha',
                    'unit_price' => 50000,
                    'shipping_fee' => 5000,
                    'lead_time_days' => 5,
                    'payment_terms' => 'Net 30',
                ],
                [
                    'name' => 'Supplier Beta',
                    'unit_price' => 40000,
                    'shipping_fee' => 15000,
                    'lead_time_days' => 25,
                    'payment_terms' => 'COD',
                ],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('scenario_type', 'supplier')
        ->assertJsonPath('simulation.summary.total_compared', 2);

    // Verify zero database mutations occurred
    expect((float) $project->fresh()->actual_spend)->toBe(1200000.00)
        ->and((int) $material->fresh()->quantity_available)->toBe(4);
});

it('allocates manpower and material templates to a project and enforces strict validation when resources or budget are insufficient', function () {
    $admin = User::factory()->create(['two_factor_Confirmed_at' => now()]);
    $admin->assignRole('Admin');

    $project = Project::factory()->create([
        'name' => 'Apex Tower Spec Allocation',
        'status' => 'ongoing',
        'contract_price' => 1000000.00,
        'actual_spend' => 600000.00,
        'start_date' => now()->subDays(10),
        'target_completion_date' => now()->addDays(60),
        'created_by' => $admin->id,
    ]);

    $material = Resource::create([
        'name' => 'Traction Machine Drive',
        'resource_code' => 'MAT-ALLOC-001',
        'type' => 'Material',
        'model' => 'TMD-800',
        'quantity_available' => 5,
        'price' => 100000.00,
        'unit_cost' => 85000.00,
        'demand_status' => 'fast-moving',
        'condition' => 'Good',
        'created_by' => $admin->id,
    ]);

    $engineer = Personnel::create([
        'employee_id' => 'EMP-2026950',
        'first_name' => 'Elena',
        'last_name' => 'Cruz',
        'date_of_birth' => '1992-07-19',
        'address' => 'Pasig City',
        'expertise' => Personnel::EXPERTISE_SITE_ENGINEER,
        'project_id' => null,
        'created_by' => $admin->id,
    ]);

    // 1. Strict Validation Failure: Not Enough Manpower (requesting 2 Site Engineers when only 1 is available)
    $this->actingAs($admin)
        ->withSession(['two_factor_verified' => true])
        ->postJson(route('decision-support.allocate'), [
            'project_id' => $project->id,
            'template_name' => 'Test Spec Template',
            'roles' => [
                ['expertise' => Personnel::EXPERTISE_SITE_ENGINEER, 'count' => 2],
            ],
            'materials' => [],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['roles']);

    // 2. Strict Validation Failure: Not Enough Material Stock (requesting 10 units when only 5 in stock)
    $this->actingAs($admin)
        ->withSession(['two_factor_verified' => true])
        ->postJson(route('decision-support.allocate'), [
            'project_id' => $project->id,
            'template_name' => 'Test Spec Template',
            'roles' => [],
            'materials' => [
                ['resource_id' => $material->id, 'quantity' => 10],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['materials']);

    // 3. Strict Validation Failure: Exceeds Remaining Project Budget (5 units * 100k = 500k > 400k remaining budget)
    $this->actingAs($admin)
        ->withSession(['two_factor_verified' => true])
        ->postJson(route('decision-support.allocate'), [
            'project_id' => $project->id,
            'template_name' => 'Test Spec Template',
            'roles' => [],
            'materials' => [
                ['resource_id' => $material->id, 'quantity' => 5],
            ],
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['materials']);

    // 4. Successful Allocation: 1 Site Engineer + 2 Traction Machine Drives (200k <= 400k remaining budget)
    $this->actingAs($admin)
        ->withSession(['two_factor_verified' => true])
        ->postJson(route('decision-support.allocate'), [
            'project_id' => $project->id,
            'template_name' => 'Spec-Matched 8-Stop Template',
            'roles' => [
                ['expertise' => Personnel::EXPERTISE_SITE_ENGINEER, 'count' => 1],
            ],
            'materials' => [
                ['resource_id' => $material->id, 'quantity' => 2],
            ],
        ])
        ->assertOk()
        ->assertJsonPath('allocation.personnel_assigned_count', 1)
        ->assertJsonPath('allocation.materials_allocated_count', 1);

    // Verify automatic reflection on the project, personnel, and catalog stock
    expect($engineer->fresh()->project_id)->toBe($project->id)
        ->and((int) $material->fresh()->quantity_available)->toBe(3)
        ->and((float) $project->fresh()->actual_spend)->toBe(800000.00);
});
