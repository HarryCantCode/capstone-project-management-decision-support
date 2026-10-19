<?php

use App\Models\Personnel;
use App\Models\Project;
use App\Models\Resource;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('unauthenticated users cannot access global search endpoint', function () {
    $this->getJson(route('global-search', ['q' => 'Elevator']))
        ->assertUnauthorized();
});

test('global search with empty query returns empty results structure', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->getJson(route('global-search', ['q' => '']))
        ->assertOk()
        ->assertJson([
            'query' => '',
            'projects' => [],
            'manpower' => [],
            'resources' => [],
            'total' => 0,
        ]);
});

test('manager and admin can search across projects, manpower, and resources in real-time', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    // 1. Create matching Project
    $project = Project::factory()->create([
        'name' => 'Highland Tower Elevator Overhaul',
        'project_code' => 'PRJ-2026-901',
        'client_name' => 'Highland Properties Inc.',
        'city' => 'Taguig City',
        'status' => 'ongoing',
    ]);

    // 2. Create non-matching Project
    Project::factory()->create([
        'name' => 'Unrelated Warehouse HVAC',
        'project_code' => 'PRJ-2026-902',
        'client_name' => 'Apex Corp',
        'city' => 'Cebu City',
    ]);

    // 3. Create matching Personnel (Manpower)
    $personnel = Personnel::create([
        'employee_id' => 'EMP-2026991',
        'first_name' => 'Eduardo',
        'last_name' => 'Highland',
        'date_of_birth' => '1990-05-15',
        'address' => 'Makati City',
        'expertise' => 'Site Engineer',
        'project_id' => $project->id,
        'created_by' => $manager->id,
    ]);

    // 4. Create matching Resource
    $resource = Resource::factory()->create([
        'name' => 'Highland Rigging Hoist Cable',
        'resource_code' => 'EQ-2026-881',
        'type' => 'equipment',
        'quantity_available' => 4,
        'condition' => 'good',
        'created_by' => $manager->id,
        'updated_by' => $manager->id,
    ]);

    $response = $this->actingAs($manager)
        ->getJson(route('global-search', ['q' => 'Highland']))
        ->assertOk()
        ->assertJsonStructure([
            'query',
            'projects' => [['id', 'type', 'code', 'title', 'subtitle', 'meta', 'status', 'status_class', 'url']],
            'manpower' => [['id', 'type', 'code', 'title', 'subtitle', 'meta', 'status', 'status_class', 'url']],
            'resources' => [['id', 'type', 'code', 'title', 'subtitle', 'meta', 'status', 'status_class', 'url']],
            'total',
        ]);

    $data = $response->json();

    expect($data['total'])->toBe(3)
        ->and($data['projects'])->toHaveCount(1)
        ->and($data['projects'][0]['title'])->toBe('Highland Tower Elevator Overhaul')
        ->and($data['projects'][0]['code'])->toBe('PRJ-2026-901')
        ->and($data['projects'][0]['url'])->toBe(route('projects.show', $project))
        ->and($data['manpower'])->toHaveCount(1)
        ->and($data['manpower'][0]['title'])->toContain('Eduardo Highland')
        ->and($data['manpower'][0]['code'])->toBe('EMP-2026991')
        ->and($data['manpower'][0]['status'])->toBe('Assigned')
        ->and($data['resources'])->toHaveCount(1)
        ->and($data['resources'][0]['title'])->toBe('Highland Rigging Hoist Cable')
        ->and($data['resources'][0]['code'])->toBe('EQ-2026-881');
});

test('staff users can only search resources and cannot access projects or manpower data', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Staff');

    // Matching project, manpower, and resource
    Project::factory()->create([
        'name' => 'Scaffold Installation Project',
    ]);

    Personnel::create([
        'employee_id' => 'EMP-2026992',
        'first_name' => 'Scaffold',
        'last_name' => 'Inspector',
        'date_of_birth' => '1992-08-20',
        'address' => 'Pasig City',
        'expertise' => 'Safety Officer',
        'created_by' => $staff->id,
    ]);

    $resource = Resource::factory()->create([
        'name' => 'Heavy Duty Scaffold Clamp Set',
        'resource_code' => 'EQ-2026-772',
        'type' => 'material',
        'quantity_available' => 50,
        'condition' => 'good',
        'created_by' => $staff->id,
        'updated_by' => $staff->id,
    ]);

    $response = $this->actingAs($staff)
        ->getJson(route('global-search', ['q' => 'Scaffold']))
        ->assertOk();

    $data = $response->json();

    // Staff cannot view projects or manpower per policy
    expect($data['projects'])->toBeEmpty()
        ->and($data['manpower'])->toBeEmpty()
        ->and($data['resources'])->toHaveCount(1)
        ->and($data['resources'][0]['title'])->toBe('Heavy Duty Scaffold Clamp Set')
        ->and($data['total'])->toBe(1);
});

test('topbar in application layout renders functional search input and dropdown markup', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $this->actingAs($manager)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('globalSearch()', false)
        ->assertSee('Search projects, manpower, resources...', false)
        ->assertSee('class="topbar-search-input"', false)
        ->assertSee('class="search-dropdown"', false)
        ->assertDontSee('Project Management System');
});
