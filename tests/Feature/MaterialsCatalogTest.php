<?php

use App\Models\Resource;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('staff or manager can view materials catalog with correct columns and data', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $material = Resource::factory()->create([
        'name' => 'Electric Wire Rope Hoist',
        'resource_code' => 'DEX-MAT001',
        'model' => '"NH" model NHHY 2T Cargo lift',
        'quantity_available' => 5,
        'price' => 546400.00,
        'unit_cost' => 480000.00,
        'demand_status' => 'fast-moving',
        'description' => 'Heavy hoist unit',
    ]);

    $response = $this->actingAs($manager)->get(route('resources.index'));

    $response->assertOk()
        ->assertSee('Materials Catalog')
        ->assertSee('DEX-MAT001')
        ->assertSee('Electric Wire Rope Hoist')
        ->assertSee('"NH" model NHHY 2T Cargo lift')
        ->assertSee('546,400.00')
        ->assertSee('Fast-moving');
});

test('manager can add new material to catalog with auto-generated DEX-MAT code', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $response = $this->actingAs($manager)
        ->post(route('resources.store'), [
            'name' => 'Cargo Steel Cage',
            'model' => 'Steel channel frameworks with guide shoes',
            'quantity_available' => 4,
            'price' => 454800.00,
            'unit_cost' => 395000.00,
            'demand_status' => 'fast-moving',
            'description' => 'Fabricated structural cage',
        ]);

    $response->assertRedirect(route('resources.index'));

    $created = Resource::query()->where('name', 'Cargo Steel Cage')->first();
    expect($created)->not->toBeNull();
    expect($created->resource_code)->toMatch('/^DEX-MAT\d{3,}$/');
    expect($created->quantity_available)->toBe(4);
});

test('manager can update and configure existing material in catalog with fixed item code', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $material = Resource::factory()->create([
        'name' => 'Old Cage',
        'resource_code' => 'DEX-MAT003',
        'quantity_available' => 2,
        'price' => 200000.00,
        'unit_cost' => 150000.00,
        'demand_status' => 'slow-moving',
    ]);

    $response = $this->actingAs($manager)
        ->put(route('resources.update', $material), [
            'name' => 'Updated Steel Cage',
            'resource_code' => 'DEX-MAT999', // Attempt to tamper should be ignored
            'model' => 'Enhanced safety interlock model',
            'quantity_available' => 6,
            'price' => 250000.00,
            'unit_cost' => 180000.00,
            'demand_status' => 'fast-moving',
            'description' => 'Upgraded model',
        ]);

    $response->assertRedirect(route('resources.index'));

    $fresh = $material->fresh();
    expect($fresh->name)->toBe('Updated Steel Cage');
    expect($fresh->resource_code)->toBe('DEX-MAT003'); // Code remains fixed
    expect($fresh->quantity_available)->toBe(6);
    expect($fresh->demand_status)->toBe('fast-moving');
});

test('manager can delete single material from catalog', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $material = Resource::factory()->create([
        'resource_code' => 'DEX-MAT008',
    ]);

    $response = $this->actingAs($manager)
        ->delete(route('resources.destroy', $material));

    $response->assertRedirect(route('resources.index'));

    $this->assertSoftDeleted('resources', [
        'id' => $material->id,
    ]);
});

test('manager can bulk delete selected materials from catalog', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $mat1 = Resource::factory()->create(['resource_code' => 'DEX-MAT010']);
    $mat2 = Resource::factory()->create(['resource_code' => 'DEX-MAT011']);
    $mat3 = Resource::factory()->create(['resource_code' => 'DEX-MAT012']);

    $response = $this->actingAs($manager)
        ->delete(route('resources.bulk-destroy'), [
            'ids' => [$mat1->id, $mat2->id],
        ]);

    $response->assertRedirect(route('resources.index'));

    $this->assertSoftDeleted('resources', ['id' => $mat1->id]);
    $this->assertSoftDeleted('resources', ['id' => $mat2->id]);
    $this->assertNotSoftDeleted('resources', ['id' => $mat3->id]);
});
