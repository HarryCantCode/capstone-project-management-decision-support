<?php

use App\Models\Personnel;
use App\Models\Project;
use App\Models\ProjectCost;
use App\Models\ProjectPersonnelHistory;
use App\Models\Resource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(function () {
    Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Manager', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Inventory Staff', 'guard_name' => 'web']);
    Role::firstOrCreate(['name' => 'Staff', 'guard_name' => 'web']);
});

test('admin and manager can access reports module index with real-time metrics', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    Project::factory()->create([
        'name' => 'Skyline Tower Passenger Elevator',
        'project_code' => 'DEX-2026-001',
        'status' => 'ongoing',
        'contract_price' => 3500000.00,
        'actual_spend' => 1200000.00,
        'created_by' => $manager->id,
    ]);

    Resource::create([
        'name' => 'Gearless Traction Machine',
        'resource_code' => 'DEX-MAT001',
        'type' => 'material',
        'model' => 'GTM-1000',
        'quantity_available' => 8,
        'price' => 250000.00,
        'unit_cost' => 195000.00,
        'demand_status' => 'fast-moving',
        'created_by' => $manager->id,
    ]);

    Personnel::create([
        'employee_id' => 'EMP-2026001',
        'first_name' => 'Carlos',
        'last_name' => 'Mendoza',
        'date_of_birth' => '1988-03-12',
        'address' => '100 Ayala Ave, Makati',
        'expertise' => Personnel::EXPERTISE_SITE_ENGINEER,
        'created_by' => $manager->id,
    ]);

    $response = $this->actingAs($manager)->get(route('reports.index'));

    $response->assertStatus(200);
    $response->assertSee('Reports &amp; Insights', false);
    $response->assertSee('Skyline Tower Passenger Elevator');
    $response->assertSee('Gearless Traction Machine');
    $response->assertSee('Carlos Mendoza');
});

test('inventory staff is forbidden from accessing reports module', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Inventory Staff');

    $this->actingAs($staff)->get(route('reports.index'))->assertStatus(403);
});

test('project pdf export succeeds with overview, financials, elevator specs, expenditures, and manpower', function () {
    $admin = User::factory()->create(['two_factor_confirmed_at' => null]);
    $admin->assignRole('Manager');

    $project = Project::factory()->create([
        'name' => 'Vertex Corporate Center Elevator',
        'project_code' => 'DEX-2026-010',
        'client_name' => 'Vertex Realty Corp.',
        'status' => 'ongoing',
        'project_type' => 'Commercial',
        'rated_capacity' => '1000 kg (13 Persons)',
        'lifting_speed' => '1.75 m/s',
        'no_of_stops' => 15,
        'cage_dimension' => '1600mm x 1400mm x 2300mm',
        'wall_and_ceiling' => 'Hairline Stainless Steel',
        'flooring' => 'Granite Tile',
        'door_operation' => '2-Panel Center Opening',
        'power_source' => '380V / 3-Phase / 60Hz',
        'operation' => 'Simplex Full Collective',
        'contract_price' => 4800000.00,
        'actual_spend' => 450000.00,
        'created_by' => $admin->id,
    ]);

    ProjectCost::create([
        'project_id' => $project->id,
        'description' => 'Guide Rail Brackets & Fasteners',
        'cost_type' => 'materials',
        'amount' => 450000.00,
        'quantity' => 30,
        'incurred_date' => now()->toDateString(),
        'created_by' => $admin->id,
    ]);

    Personnel::create([
        'employee_id' => 'EMP-2026050',
        'first_name' => 'Roberto',
        'last_name' => 'Villanueva',
        'date_of_birth' => '1985-07-19',
        'address' => 'Quezon City',
        'expertise' => Personnel::EXPERTISE_FOREMAN,
        'project_id' => $project->id,
        'date_assigned' => now()->toDateString(),
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->get(route('reports.project.pdf', $project));

    $response->assertStatus(200);
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('project pdf export does not error on a project with no expenditures or manpower yet', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $emptyProject = Project::factory()->create([
        'name' => 'New Empty Project',
        'project_code' => 'DEX-2026-099',
        'status' => 'pending',
        'contract_price' => 1500000.00,
        'actual_spend' => 0.00,
        'created_by' => $manager->id,
    ]);

    $response = $this->actingAs($manager)->get(route('reports.project.pdf', $emptyProject));

    $response->assertStatus(200);
    expect($response->headers->get('content-type'))->toContain('application/pdf');
});

test('materials list can be exported by selected materials or filtered by fast-moving and slow-moving', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $fastItem = Resource::create([
        'name' => 'Steel Wire Rope 10mm',
        'resource_code' => 'DEX-MAT010',
        'type' => 'material',
        'model' => 'SWR-10',
        'quantity_available' => 25,
        'price' => 12000.00,
        'unit_cost' => 8500.00,
        'demand_status' => 'fast-moving',
        'created_by' => $manager->id,
    ]);

    $slowItem = Resource::create([
        'name' => 'Custom Panoramic Glass Panel',
        'resource_code' => 'DEX-MAT011',
        'type' => 'material',
        'model' => 'PGP-200',
        'quantity_available' => 2,
        'price' => 95000.00,
        'unit_cost' => 70000.00,
        'demand_status' => 'slow-moving',
        'created_by' => $manager->id,
    ]);

    // 1. Export filtered by fast-moving (PDF)
    $pdfResponse = $this->actingAs($manager)->post(route('reports.materials.export'), [
        'export_mode' => 'filtered',
        'demand_status' => 'fast-moving',
        'format' => 'pdf',
    ]);
    $pdfResponse->assertStatus(200);
    expect($pdfResponse->headers->get('content-type'))->toContain('application/pdf');

    // 2. Export selected materials (CSV)
    $csvResponse = $this->actingAs($manager)->post(route('reports.materials.export'), [
        'export_mode' => 'selected',
        'material_ids' => [$slowItem->id],
        'format' => 'csv',
    ]);
    $csvResponse->assertStatus(200);
    $csvContent = $csvResponse->streamedContent();
    expect($csvContent)->toContain('DEX-MAT011')
        ->and($csvContent)->not->toContain('DEX-MAT010');
});

test('manpower list can be exported by selected personnel or expertise filter with optional recent projects', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create([
        'name' => 'BGC Commercial Lift',
        'project_code' => 'DEX-2026-055',
        'status' => 'completed',
        'created_by' => $manager->id,
    ]);

    $engineer = Personnel::create([
        'employee_id' => 'EMP-2026201',
        'first_name' => 'Rafael',
        'last_name' => 'Soriano',
        'date_of_birth' => '1991-04-10',
        'address' => 'Taguig City',
        'expertise' => Personnel::EXPERTISE_SITE_ENGINEER,
        'created_by' => $manager->id,
    ]);

    ProjectPersonnelHistory::create([
        'project_id' => $project->id,
        'personnel_id' => $engineer->id,
        'assigned_at' => now()->subMonths(2)->toDateString(),
        'released_at' => now()->subDays(5)->toDateString(),
        'release_reason' => 'project_completed',
        'assigned_by' => $manager->id,
        'released_by' => $manager->id,
    ]);

    // 1. PDF Export filtered by expertise with recent projects included
    $pdfResponse = $this->actingAs($manager)->post(route('reports.manpower.export'), [
        'export_mode' => 'filtered',
        'expertise' => Personnel::EXPERTISE_SITE_ENGINEER,
        'include_recent_projects' => true,
        'format' => 'pdf',
    ]);
    $pdfResponse->assertStatus(200);
    expect($pdfResponse->headers->get('content-type'))->toContain('application/pdf');

    // 2. CSV Export of selected personnel with recent projects included
    $csvResponse = $this->actingAs($manager)->post(route('reports.manpower.export'), [
        'export_mode' => 'selected',
        'personnel_ids' => [$engineer->id],
        'include_recent_projects' => 1,
        'format' => 'csv',
    ]);
    $csvResponse->assertStatus(200);
    $csvContent = $csvResponse->streamedContent();
    expect($csvContent)->toContain('EMP-2026201')
        ->and($csvContent)->toContain('DEX-2026-055')
        ->and($csvContent)->toContain('BGC Commercial Lift')
        ->and($csvContent)->not->toContain('Taguig City');
});

test('revenue data can be exported as pdf and csv without taxes for monthly and yearly comparisons', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    Project::factory()->create([
        'name' => 'Makati Tower Lift',
        'project_code' => 'DEX-2026-070',
        'status' => 'ongoing',
        'contract_price' => 5000000.00,
        'actual_spend' => 2000000.00,
        'start_date' => now()->toDateString(),
        'created_by' => $manager->id,
    ]);

    // 1. Monthly Revenue PDF Export
    $pdfResponse = $this->actingAs($manager)->post(route('reports.revenue.export'), [
        'comparison_mode' => 'monthly',
        'year' => (string) now()->year,
        'scope' => 'active',
        'format' => 'pdf',
    ]);
    $pdfResponse->assertStatus(200);
    expect($pdfResponse->headers->get('content-type'))->toContain('application/pdf');

    // 2. Yearly Revenue CSV Export
    $csvResponse = $this->actingAs($manager)->post(route('reports.revenue.export'), [
        'comparison_mode' => 'yearly',
        'scope' => 'active',
        'format' => 'csv',
    ]);
    $csvResponse->assertStatus(200);
    $csvContent = $csvResponse->streamedContent();
    expect($csvContent)->toContain('WITHOUT TAXES')
        ->and($csvContent)->toContain('DEX-2026-070')
        ->and($csvContent)->toContain('5000000.00')
        ->and($csvContent)->toContain('2000000.00')
        ->and($csvContent)->toContain('3000000.00');
});
