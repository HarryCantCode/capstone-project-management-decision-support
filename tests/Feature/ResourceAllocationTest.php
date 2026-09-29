<?php

use App\Models\Project;
use App\Models\Resource;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleSeeder::class));

test('manager can allocate available stock to an active project', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $project = Project::factory()->create(['status' => 'ongoing']);
    $resource = Resource::factory()->create(['quantity_available' => 10]);

    $this->actingAs($manager)
        ->post(route('resources.allocate.store', $resource), [
            'project_id' => $project->id,
            'quantity' => 4,
            'notes' => 'Required for the first installation phase.',
        ])
        ->assertRedirect(route('resources.show', $resource));

    expect($resource->fresh()->quantity_available)->toBe(6);
    $this->assertDatabaseHas('resource_allocations', [
        'project_id' => $project->id,
        'resource_id' => $resource->id,
        'quantity' => 4,
        'created_by' => $manager->id,
    ]);
});

test('allocation is rejected when requested quantity exceeds available stock', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');
    $project = Project::factory()->create(['status' => 'ongoing']);
    $resource = Resource::factory()->create(['quantity_available' => 2]);

    $this->actingAs($manager)
        ->post(route('resources.allocate.store', $resource), [
            'project_id' => $project->id,
            'quantity' => 3,
        ])
        ->assertSessionHasErrors('quantity');

    expect($resource->fresh()->quantity_available)->toBe(2);
    $this->assertDatabaseCount('resource_allocations', 0);
});

test('user without required role cannot access resource allocation routes', function () {
    $user = User::factory()->create();
    $resource = Resource::factory()->create();

    $this->actingAs($user)
        ->get(route('resources.index'))
        ->assertForbidden();

    $this->actingAs($user)
        ->post(route('resources.allocate.store', $resource), [
            'project_id' => Project::factory()->create()->id,
            'quantity' => 1,
        ])
        ->assertForbidden();
});
