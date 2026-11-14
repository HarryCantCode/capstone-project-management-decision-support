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

function makeSubtaskRequest(User $staff, bool $withProof = false): StaffPendingAction
{
    $project = Project::factory()->create();
    $task = \App\Models\ProjectTask::create([
        'project_id' => $project->id,
        'task_name' => 'Foundation Works',
        'start_date' => now()->toDateString(),
        'end_date' => now()->addDays(5)->toDateString(),
        'status' => 'pending',
        'sort_order' => 1,
    ]);
    $subtask = \App\Models\ProjectSubtask::create([
        'project_task_id' => $task->id,
        'title' => 'Pour concrete',
        'due_date' => now()->addDay()->toDateString(),
        'is_completed' => false,
        'sort_order' => 1,
    ]);

    $files = $withProof ? [[
        'file_name' => 'proof.jpg', 'file_path' => 'project-files/none/proof.jpg',
        'file_size' => 10, 'mime_type' => 'image/jpeg',
    ]] : [];

    return StaffPendingAction::create([
        'user_id' => $staff->id,
        'project_id' => $project->id,
        'action_type' => 'complete_subtask',
        'status' => 'pending',
        'payload' => [
            'task_id' => $task->id, 'task_name' => $task->task_name,
            'subtask_id' => $subtask->id, 'subtask_name' => $subtask->title,
            'subtask_title' => $subtask->title, 'notes' => 'Done',
            'files' => $files, 'file_type' => 'task_attachment',
        ],
    ]);
}

it('emails the staff user on subtask completion approve and reject', function (bool $withProof) {
    Mail::fake();

    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $staff = User::factory()->create();
    $staff->assignRole('Staff');
    $this->actingAs($admin);

    $service = app(StaffPendingActionService::class);

    $approve = $service->approve(makeSubtaskRequest($staff, $withProof), $admin->id);
    $reject = $service->reject(makeSubtaskRequest($staff, $withProof), $admin->id);

    expect($approve['success'])->toBeTrue();
    expect($reject['success'])->toBeTrue();
    Mail::assertSent(StaffActionResultMail::class, fn ($m) => $m->hasTo($staff->email) && $m->decision === 'approved');
    Mail::assertSent(StaffActionResultMail::class, fn ($m) => $m->hasTo($staff->email) && $m->decision === 'rejected');
})->with([[false], [true]]);

it('actually renders and delivers the subtask verdict email through a real mailer', function (string $decision) {
    config(['mail.default' => 'array']);

    $admin = User::factory()->create();
    $admin->assignRole('Admin');
    $staff = User::factory()->create();
    $staff->assignRole('Staff');
    $this->actingAs($admin);

    $action = makeSubtaskRequest($staff, true);
    $action->update(['status' => $decision, 'reviewed_by' => $admin->id, 'reviewed_at' => now()]);

    $html = (new StaffActionResultMail($staff, $action->fresh(), $decision))->render();
    expect($html)->toContain('Pour concrete');

    app(\App\Services\EmailNotificationService::class)->sendStaffActionResultEmail($action->fresh(), $decision);

    $sent = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
    expect($sent)->toHaveCount(1);
})->with(['approved', 'rejected']);

it('uses a unique subject for each verdict so mail clients do not thread them together', function () {
    $admin = User::factory()->create();
    $staff = User::factory()->create();
    $a = makeSubtaskRequest($staff);
    $b = makeSubtaskRequest($staff);

    $subjectA = (new StaffActionResultMail($staff, $a, 'rejected'))->envelope()->subject;
    $subjectB = (new StaffActionResultMail($staff, $b, 'rejected'))->envelope()->subject;

    expect($subjectA)->not->toBe($subjectB)->and($subjectA)->toContain("#{$a->id}");
});

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
