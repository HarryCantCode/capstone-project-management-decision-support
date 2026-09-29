<?php

use App\Models\Project;
use App\Models\ProjectCost;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('admin can access costing index', function () {
    $admin = User::factory()->create();
    $admin->assignRole('Admin');

    $this->actingAs($admin)
        ->get(route('costing.index'))
        ->assertOk()
        ->assertViewIs('costing.index');
});

test('manager can access costing index (fixes manager costing access bug)', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->get(route('costing.index'))
        ->assertOk()
        ->assertViewIs('costing.index');
});

test('staff cannot access costing index', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Staff');

    $this->actingAs($staff)
        ->get(route('costing.index'))
        ->assertForbidden();
});

test('costing index displays real created projects and calculates totals', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project1 = Project::factory()->create([
        'name' => 'Project Alpha',
        'contract_price' => 1000000.00,
        'actual_spend' => 400000.00,
        'created_by' => $manager->id,
    ]);

    $project2 = Project::factory()->create([
        'name' => 'Project Beta',
        'contract_price' => 2000000.00,
        'actual_spend' => 1500000.00,
        'created_by' => $manager->id,
    ]);

    $response = $this->actingAs($manager)->get(route('costing.index'));

    $response->assertOk()
        ->assertSee('Project Alpha')
        ->assertSee('Project Beta')
        ->assertSee('₱1,000,000.00')
        ->assertSee('₱2,000,000.00')
        ->assertSee('₱400,000.00')
        ->assertSee('₱1,500,000.00')
        ->assertSee('₱3,000,000.00') // Total allocated
        ->assertSee('₱1,900,000.00') // Total spend
        ->assertSee('₱1,100,000.00'); // Total remaining
});

test('manager can view project costing breakdown with search and category filtering', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create([
        'name' => 'Makati Tower Elevator Project',
        'contract_price' => 5000000.00,
        'actual_spend' => 650000.00,
        'created_by' => $manager->id,
    ]);

    ProjectCost::create([
        'project_id' => $project->id,
        'description' => 'Guide rails and brackets',
        'cost_type' => 'materials',
        'amount' => 400000.00,
        'incurred_date' => '2026-08-15',
        'created_by' => $manager->id,
    ]);

    ProjectCost::create([
        'project_id' => $project->id,
        'description' => 'Rigging crane rental',
        'cost_type' => 'equipment',
        'amount' => 250000.00,
        'incurred_date' => '2026-08-20',
        'created_by' => $manager->id,
    ]);

    // 1. Basic view
    $response = $this->actingAs($manager)->get(route('costing.show', $project));
    $response->assertOk()
        ->assertSee('Makati Tower Elevator Project — Costing Breakdown')
        ->assertSee('Guide rails and brackets')
        ->assertSee('Rigging crane rental')
        ->assertSee('₱400,000.00')
        ->assertSee('₱250,000.00');

    // 2. Filter by search description
    $responseSearch = $this->actingAs($manager)->get(route('costing.show', ['project' => $project, 'search' => 'crane']));
    $responseSearch->assertOk()
        ->assertSee('Rigging crane rental')
        ->assertDontSee('Guide rails and brackets');

    // 3. Filter by category
    $responseCategory = $this->actingAs($manager)->get(route('costing.show', ['project' => $project, 'category' => 'materials']));
    $responseCategory->assertOk()
        ->assertSee('Guide rails and brackets')
        ->assertDontSee('Rigging crane rental');

    // 4. Filter by amount range
    $responseAmount = $this->actingAs($manager)->get(route('costing.show', ['project' => $project, 'min_amount' => 300000]));
    $responseAmount->assertOk()
        ->assertSee('Guide rails and brackets')
        ->assertDontSee('Rigging crane rental');
});

test('adding or deleting an expense from costing breakdown redirects back to costing view', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create([
        'contract_price' => 2000000.00,
        'actual_spend' => 0.00,
    ]);

    // 1. Add cost with redirect_to='costing'
    $response = $this->actingAs($manager)->post(route('projects.costs.store', $project), [
        'description' => 'Electrical cables and conduits',
        'cost_type' => 'materials',
        'amount' => 150000.00,
        'incurred_date' => now()->format('Y-m-d'),
        'redirect_to' => 'costing',
    ]);

    $response->assertRedirect(route('costing.show', $project));
    expect((float) $project->fresh()->actual_spend)->toBe(150000.00);

    // 2. Delete cost with referer header pointing to costing
    $cost = $project->fresh()->costs->first();
    $deleteResponse = $this->actingAs($manager)
        ->from(route('costing.show', $project))
        ->delete(route('projects.costs.destroy', ['project' => $project, 'cost' => $cost]));

    $deleteResponse->assertRedirect(route('costing.show', $project));
    expect($project->fresh()->costs)->toHaveCount(0);
});
