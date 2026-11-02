<?php

use App\Models\Project;
use App\Models\ProjectSubtask;
use App\Models\ProjectTask;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
});

test('payment status paid requires invoice attachment on update if no invoice exists', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create([
        'payment_status' => 'Partial',
    ]);

    // Updating to Paid without an invoice file should fail validation
    $this->actingAs($manager)
        ->put(route('projects.update', $project), [
            'name' => $project->name,
            'client_name' => $project->client_name,
            'address' => $project->address,
            'city' => $project->city,
            'project_type' => $project->project_type,
            'rated_capacity' => $project->rated_capacity,
            'lifting_speed' => $project->lifting_speed,
            'no_of_stops' => $project->no_of_stops,
            'cage_dimension' => $project->cage_dimension,
            'wall_and_ceiling' => $project->wall_and_ceiling,
            'flooring' => $project->flooring,
            'door_operation' => $project->door_operation,
            'power_source' => $project->power_source,
            'operation' => $project->operation,
            'payment_status' => 'Paid',
            'start_date' => $project->start_date->format('Y-m-d'),
            'target_completion_date' => $project->target_completion_date->format('Y-m-d'),
            'contract_price' => $project->contract_price,
            'update_notes' => 'Changing to Paid in full',
        ])
        ->assertSessionHasErrors(['invoice_file']);

    expect($project->fresh()->payment_status)->toBe('Partial');
});

test('payment status paid succeeds when invoice attachment is provided', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create([
        'payment_status' => 'Partial',
        'start_date' => now()->toDateString(),
        'target_completion_date' => now()->addMonths(2)->toDateString(),
    ]);

    $invoiceFile = UploadedFile::fake()->create('invoice_test.pdf', 1024, 'application/pdf');

    $this->actingAs($manager)
        ->put(route('projects.update', $project), [
            'name' => $project->name,
            'client_name' => $project->client_name,
            'address' => $project->address,
            'city' => $project->city,
            'project_type' => $project->project_type,
            'rated_capacity' => $project->rated_capacity,
            'lifting_speed' => $project->lifting_speed,
            'no_of_stops' => $project->no_of_stops,
            'cage_dimension' => $project->cage_dimension,
            'wall_and_ceiling' => $project->wall_and_ceiling,
            'flooring' => $project->flooring,
            'door_operation' => $project->door_operation,
            'power_source' => $project->power_source,
            'operation' => $project->operation,
            'payment_status' => 'Paid',
            'invoice_file' => $invoiceFile,
            'start_date' => now()->toDateString(),
            'target_completion_date' => now()->addMonths(2)->toDateString(),
            'contract_price' => $project->contract_price,
            'update_notes' => 'Paid in full with official invoice attached',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('projects.show', $project));

    $project->refresh();
    expect($project->payment_status)->toBe('Paid');
    expect($project->invoices)->toHaveCount(1);
    expect($project->files)->toHaveCount(1);
    expect($project->invoices->first()->file_name)->toBe('invoice_test.pdf');
    expect($project->invoices->first()->is_pdf)->toBeTrue();
    Storage::disk('public')->assertExists($project->invoices->first()->file_path);
});

test('payment status partial allows optional invoice attachment', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create([
        'payment_status' => 'Partial',
    ]);

    $invoiceFile = UploadedFile::fake()->create('partial_receipt.png', 500, 'image/png');

    $this->actingAs($manager)
        ->put(route('projects.update', $project), [
            'name' => $project->name,
            'client_name' => $project->client_name,
            'address' => $project->address,
            'city' => $project->city,
            'project_type' => $project->project_type,
            'rated_capacity' => $project->rated_capacity,
            'lifting_speed' => $project->lifting_speed,
            'no_of_stops' => $project->no_of_stops,
            'cage_dimension' => $project->cage_dimension,
            'wall_and_ceiling' => $project->wall_and_ceiling,
            'flooring' => $project->flooring,
            'door_operation' => $project->door_operation,
            'power_source' => $project->power_source,
            'operation' => $project->operation,
            'payment_status' => 'Partial',
            'invoice_file' => $invoiceFile,
            'start_date' => $project->start_date->format('Y-m-d'),
            'target_completion_date' => $project->target_completion_date->format('Y-m-d'),
            'contract_price' => $project->contract_price,
            'update_notes' => 'Partial payment with receipt attached',
        ])
        ->assertSessionHasNoErrors();

    $project->refresh();
    expect($project->payment_status)->toBe('Partial');
    expect($project->files)->toHaveCount(1);
    expect($project->files->first()->is_image)->toBeTrue();
});

test('manager can upload file directly to project files section', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create();

    $docFile = UploadedFile::fake()->create('blueprint.docx', 800, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    $this->actingAs($manager)
        ->post(route('projects.files.store', $project), [
            'file' => $docFile,
            'file_type' => 'general',
            'description' => 'Architectural elevator blueprint',
        ])
        ->assertRedirect(route('projects.show', $project));

    expect($project->files)->toHaveCount(1);
    $uploaded = $project->files->first();
    expect($uploaded->file_name)->toBe('blueprint.docx');
    expect($uploaded->is_word)->toBeTrue();
    expect($uploaded->description)->toBe('Architectural elevator blueprint');
    Storage::disk('public')->assertExists($uploaded->file_path);
});

test('manager can upload file to a specific subtask in the timeline', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create();
    $task = ProjectTask::create([
        'project_id' => $project->id,
        'task_name' => 'Week 1: Permits',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(6)->toDateString(),
        'status' => 'pending',
    ]);
    $subtask = ProjectSubtask::create([
        'project_task_id' => $task->id,
        'title' => 'Building Permit',
        'due_date' => now()->toDateString(),
        'is_completed' => false,
    ]);

    $permitFile = UploadedFile::fake()->create('city_hall_permit.pdf', 1200, 'application/pdf');

    $this->actingAs($manager)
        ->post(route('projects.tasks.subtasks.files.store', [$project, $task, $subtask]), [
            'file' => $permitFile,
            'description' => 'Approved LGU mechanical permit',
        ])
        ->assertRedirect(route('projects.show', $project));

    $subtask->refresh();
    expect($subtask->files)->toHaveCount(1);
    $file = $subtask->files->first();
    expect($file->file_name)->toBe('city_hall_permit.pdf');
    expect($file->project_id)->toBe($project->id);
    expect($file->file_type)->toBe('task_attachment');
    expect($file->description)->toBe('Approved LGU mechanical permit');
    Storage::disk('public')->assertExists($file->file_path);

    // Also appears in project files list
    expect($project->files)->toHaveCount(1);
});

test('manager can update file description', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create();
    $file = $project->files()->create([
        'uploaded_by' => $manager->id,
        'file_type' => 'general',
        'file_name' => 'specifications.pdf',
        'file_path' => 'projects/1/files/specifications.pdf',
        'mime_type' => 'application/pdf',
        'file_size' => 20480,
        'description' => 'Old specification notes',
    ]);

    $this->actingAs($manager)
        ->put(route('projects.files.update', [$project, $file]), [
            'description' => 'Revised elevator specifications approved by client',
        ])
        ->assertRedirect(route('projects.show', $project));

    expect($file->fresh()->description)->toBe('Revised elevator specifications approved by client');
});

test('manager can download and delete an uploaded project file', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create();
    $uploadedFile = UploadedFile::fake()->create('report.pdf', 100, 'application/pdf');

    $path = $uploadedFile->store("projects/{$project->id}/files", 'public');
    $file = $project->files()->create([
        'uploaded_by' => $manager->id,
        'file_type' => 'general',
        'file_name' => 'report.pdf',
        'file_path' => $path,
        'mime_type' => 'application/pdf',
        'file_size' => 102400,
    ]);

    // Download
    $this->actingAs($manager)
        ->get(route('projects.files.download', [$project, $file]))
        ->assertOk();

    // Delete
    $this->actingAs($manager)
        ->delete(route('projects.files.destroy', [$project, $file]))
        ->assertRedirect(route('projects.show', $project));

    expect($project->fresh()->files)->toHaveCount(0);
    Storage::disk('public')->assertMissing($path);
});

test('project show page renders project files section below financials & budget', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create();

    $response = $this->actingAs($manager)
        ->get(route('projects.show', $project));

    $response->assertOk();
    expect($response->content())->toContain('Project Files & Documents')
        ->toContain('Financials & Budget')
        ->toContain('No files uploaded yet.');
});
