<?php

use App\Models\Personnel;
use App\Models\Project;
use App\Models\ProjectCost;
use App\Models\ProjectFile;
use App\Models\StaffPendingAction;
use App\Models\User;
use App\Services\StaffPendingActionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RoleSeeder::class);
    Storage::fake('public');
});

test('logging cost requires an attached invoice file', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create([
        'contract_price' => 1000000.00,
        'actual_spend' => 0.00,
    ]);

    // Attempting to post without invoice fails with session errors
    $response = $this->actingAs($manager)
        ->from(route('costing.show', $project))
        ->post(route('projects.costs.store', $project), [
            'description' => 'Direct structural scaffolding purchase',
            'cost_type' => 'materials',
            'amount' => 50000.00,
            'incurred_date' => now()->format('Y-m-d'),
        ]);

    $response->assertSessionHasErrors('invoice');
    expect(ProjectCost::count())->toBe(0);
});

test('manager can log cost with valid invoice and auto-registers project file', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create([
        'contract_price' => 1000000.00,
        'actual_spend' => 0.00,
    ]);

    $invoiceFile = UploadedFile::fake()->create('tax-invoice-001.pdf', 250, 'application/pdf');

    $response = $this->actingAs($manager)
        ->post(route('projects.costs.store', $project), [
            'description' => 'Direct structural scaffolding purchase',
            'cost_type' => 'materials',
            'amount' => 50000.00,
            'incurred_date' => now()->format('Y-m-d'),
            'invoice' => $invoiceFile,
        ]);

    $response->assertRedirect(route('projects.show', $project));

    $cost = ProjectCost::first();
    expect($cost)->not->toBeNull()
        ->and($cost->has_invoice)->toBeTrue()
        ->and($cost->invoice_original_name)->toBe('tax-invoice-001.pdf')
        ->and($cost->invoice_mime_type)->toBe('application/pdf')
        ->and($cost->is_invoice_pdf)->toBeTrue()
        ->and($cost->is_invoice_image)->toBeFalse()
        ->and($cost->invoice_url)->toContain('tax-invoice-001.pdf');

    // Confirm physical disk storage
    Storage::disk('public')->assertExists($cost->invoice_path);

    // Confirm auto-registration into ProjectFile
    $projectFile = ProjectFile::where('project_id', $project->id)
        ->where('file_type', ProjectFile::TYPE_PROJECT_INVOICE)
        ->first();

    expect($projectFile)->not->toBeNull()
        ->and($projectFile->file_name)->toBe('tax-invoice-001.pdf')
        ->and($projectFile->file_path)->toBe($cost->invoice_path);
});

test('staff logging cost routes to pending action with invoice preserved', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Staff');

    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create([
        'contract_price' => 500000.00,
        'actual_spend' => 0.00,
    ]);

    $invoiceImage = UploadedFile::fake()->image('receipt-photo.jpg', 600, 800);

    $response = $this->actingAs($staff)
        ->post(route('projects.costs.store', $project), [
            'description' => 'Emergency wiring conduit',
            'cost_type' => 'materials',
            'amount' => 12500.00,
            'incurred_date' => now()->format('Y-m-d'),
            'invoice' => $invoiceImage,
        ]);

    $response->assertRedirect();
    $response->assertSessionHas('success');

    // Cost is NOT yet created in project_costs
    expect(ProjectCost::count())->toBe(0);

    // StaffPendingAction created
    $pendingAction = StaffPendingAction::first();
    expect($pendingAction)->not->toBeNull()
        ->and($pendingAction->action_type)->toBe('add_expense')
        ->and($pendingAction->status)->toBe('pending')
        ->and($pendingAction->payload['description'])->toBe('Emergency wiring conduit')
        ->and($pendingAction->payload['invoice_original_name'])->toBe('receipt-photo.jpg')
        ->and($pendingAction->payload['invoice_path'])->not->toBeEmpty();

    Storage::disk('public')->assertExists($pendingAction->payload['invoice_path']);

    // Check modal format returns invoice details
    $service = app(StaffPendingActionService::class);
    $modalData = $service->formatActionForModal($pendingAction);

    expect($modalData['invoice_exists'])->toBeTrue()
        ->and($modalData['invoice_name'])->toBe('receipt-photo.jpg')
        ->and($modalData['invoice_url'])->not->toBeNull();

    // Approving the pending action persists the cost and project file
    $service->approve($pendingAction, $manager, 'Verified with supplier receipt');

    expect(ProjectCost::count())->toBe(1);
    $persistedCost = ProjectCost::first();
    expect($persistedCost->invoice_original_name)->toBe('receipt-photo.jpg')
        ->and($persistedCost->has_invoice)->toBeTrue()
        ->and($persistedCost->is_invoice_image)->toBeTrue();

    // ProjectFile is registered
    $fileRecord = ProjectFile::where('project_id', $project->id)->first();
    expect($fileRecord)->not->toBeNull()
        ->and($fileRecord->file_name)->toBe('receipt-photo.jpg');
});

test('rejecting staff pending expense cleans up the stored invoice file', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Staff');

    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $project = Project::factory()->create();
    $invoiceFile = UploadedFile::fake()->create('unauthorized.pdf', 100);

    $this->actingAs($staff)->post(route('projects.costs.store', $project), [
        'description' => 'Unapproved tool purchase',
        'cost_type' => 'equipment',
        'amount' => 7500.00,
        'incurred_date' => now()->format('Y-m-d'),
        'invoice' => $invoiceFile,
    ]);

    $pendingAction = StaffPendingAction::first();
    $storedPath = $pendingAction->payload['invoice_path'];
    Storage::disk('public')->assertExists($storedPath);

    // Rejecting action deletes stored invoice
    $service = app(StaffPendingActionService::class);
    $service->reject($pendingAction, $manager, 'Expense not authorized for this phase');

    Storage::disk('public')->assertMissing($storedPath);
    expect(ProjectCost::count())->toBe(0);
});

test('project creation requires invoice on each initial expense row', function () {
    $manager = User::factory()->create();
    $manager->assignRole('Manager');

    $personnel = Personnel::factory()->create();

    $projectData = [
        'name' => 'Torre Lorenzo High-Rise Installation',
        'client_name' => 'Torre Lorenzo Dev Corp',
        'address' => 'Taft Avenue',
        'city' => 'Manila',
        'project_type' => 'Commercial',
        'rated_capacity' => '1000kg',
        'lifting_speed' => '1.5m/s',
        'no_of_stops' => 20,
        'cage_dimension' => '1600x1400',
        'wall_and_ceiling' => 'Hairline Stainless Steel',
        'flooring' => 'Granite Tile',
        'door_operation' => 'Center Opening',
        'power_source' => '380V 3Phase 60Hz',
        'operation' => 'Simplex Selective Collective',
        'payment_status' => 'Paid',
        'contract_price' => 4500000.00,
        'start_date' => now()->format('Y-m-d'),
        'target_completion_date' => now()->addMonths(6)->format('Y-m-d'),
        'personnel_ids' => [$personnel->id],
        'invoice_file' => UploadedFile::fake()->create('project-master-invoice.pdf', 300),
        'expenses' => [
            [
                'mode' => 'manual',
                'description' => 'Initial Site Survey and Mobilization',
                'cost_type' => 'other',
                'quantity' => 1,
                'amount' => '35000.00',
                // invoice intentionally missing
            ],
        ],
    ];

    $response = $this->actingAs($manager)
        ->from(route('projects.create'))
        ->post(route('projects.store'), $projectData);

    $response->assertSessionHasErrors('expenses.0.invoice');
    expect(Project::where('name', 'Torre Lorenzo High-Rise Installation')->count())->toBe(0);

    // Now supply the invoice for the initial expense row
    $projectData['expenses'][0]['invoice'] = UploadedFile::fake()->create('survey-invoice.pdf', 150);

    $successResponse = $this->actingAs($manager)->post(route('projects.store'), $projectData);
    $successResponse->assertRedirect();

    $createdProject = Project::where('name', 'Torre Lorenzo High-Rise Installation')->first();
    expect($createdProject)->not->toBeNull();

    $createdCost = ProjectCost::where('project_id', $createdProject->id)->first();
    expect($createdCost)->not->toBeNull()
        ->and($createdCost->invoice_original_name)->toBe('survey-invoice.pdf')
        ->and($createdCost->has_invoice)->toBeTrue();

    Storage::disk('public')->assertExists($createdCost->invoice_path);
});
