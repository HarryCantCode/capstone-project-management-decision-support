<?php

use App\Mail\StaffActionResultMail;
use App\Models\Project;
use App\Models\StaffPendingAction;
use App\Models\User;
use App\Services\StaffPendingActionService;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->seed(RoleSeeder::class));

function makeExpenseRequest(User $staff): StaffPendingAction
{
    $project = Project::factory()->create();

    return StaffPendingAction::create([
        'user_id' => $staff->id,
        'project_id' => $project->id,
        'action_type' => 'add_expense',
        'status' => 'pending',
        'payload' => ['description' => 'Cement', 'amount' => 500, 'cost_type' => 'material'],
    ]);
}

it('emails the staff user on every approve and reject', function () {
    Mail::fake();

    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $staff = User::factory()->create();
    $staff->assignRole('Staff');

    $this->actingAs($admin);
    $service = app(StaffPendingActionService::class);

    for ($i = 0; $i < 3; $i++) {
        $service->approve(makeExpenseRequest($staff), $admin->id);
        $service->reject(makeExpenseRequest($staff), $admin->id);
    }

    Mail::assertSent(StaffActionResultMail::class, 6);
    Mail::assertSent(StaffActionResultMail::class, fn ($m) => $m->hasTo($staff->email) && $m->decision === 'rejected');
    Mail::assertSent(StaffActionResultMail::class, fn ($m) => $m->hasTo($staff->email) && $m->decision === 'approved');
});
