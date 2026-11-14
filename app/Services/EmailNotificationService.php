<?php

namespace App\Services;

use App\Mail\MaterialLowStockMail;
use App\Mail\PasswordChangedMail;
use App\Mail\ProjectCompletedMail;
use App\Mail\ProjectCreatedMail;
use App\Mail\StaffActionPendingReviewMail;
use App\Mail\StaffActionResultMail;
use App\Mail\TaskAddedMail;
use App\Mail\TaskDueSoonMail;
use App\Mail\WarrantyWarningMail;
use App\Models\Project;
use App\Models\ProjectSubtask;
use App\Models\ProjectTask;
use App\Models\Resource;
use App\Models\StaffPendingAction;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * EmailNotificationService — dispatches transactional email notifications.
 *
 * Handles targeted distribution across active users (Admin, Manager, Staff)
 * and role-restricted actions (Manager/Admin review alerts, Staff-only outcome notices).
 * Wrapped in defensive error handling to ensure email transport glitches never abort
 * the primary database mutation or user flow.
 */
class EmailNotificationService
{
    /**
     * Action 1: Adding a major task or specific sub task.
     * Dispatches notification email across active operational users.
     */
    public function sendTaskAddedEmail(
        Project $project,
        ProjectTask $task,
        ?string $subtaskTitle = null,
        ?ProjectSubtask $subtask = null,
    ): void {
        $recipients = $this->getActiveOperationalUsers();

        $this->dispatchToRecipients($recipients, function (User $recipient) use ($project, $task, $subtaskTitle, $subtask) {
            return new TaskAddedMail($recipient, $project, $task, $subtaskTitle, $subtask);
        });
    }

    /**
     * Action 2: A new project was created.
     * Dispatches notification email across active operational users.
     */
    public function sendProjectCreatedEmail(Project $project): void
    {
        $recipients = $this->getActiveOperationalUsers();

        $this->dispatchToRecipients($recipients, function (User $recipient) use ($project) {
            return new ProjectCreatedMail($recipient, $project);
        });
    }

    /**
     * Action 3: A sub / specific task is about to be due.
     * Dispatches notification email across active operational users.
     */
    public function sendTaskDueSoonEmail(
        Project $project,
        ProjectTask $task,
        ?ProjectSubtask $subtask = null,
    ): void {
        $recipients = $this->getActiveOperationalUsers();

        $this->dispatchToRecipients($recipients, function (User $recipient) use ($project, $task, $subtask) {
            return new TaskDueSoonMail($recipient, $project, $task, $subtask);
        });
    }

    /**
     * Action 4: A catalog material has low stock (<= 8 units).
     * Dispatches warning notification email across active operational users.
     */
    public function sendMaterialLowStockEmail(Resource $resource): void
    {
        $recipients = $this->getActiveOperationalUsers();

        $this->dispatchToRecipients($recipients, function (User $recipient) use ($resource) {
            return new MaterialLowStockMail($recipient, $resource);
        });
    }

    /**
     * Action 5: A project was completed.
     * Dispatches congratulatory completion notification email across active operational users.
     */
    public function sendProjectCompletedEmail(Project $project): void
    {
        $recipients = $this->getActiveOperationalUsers();

        $this->dispatchToRecipients($recipients, function (User $recipient) use ($project) {
            return new ProjectCompletedMail($recipient, $project);
        });
    }

    /**
     * Action 6: A staff user submitted a request (e.g. adding an expense or completing a task).
     * This email is strictly sent to Manager and Admin users so they can review the pending request.
     */
    public function sendStaffActionPendingReviewEmail(StaffPendingAction $pendingAction): void
    {
        // Enforce role restriction: managers and admins only
        $recipients = $this->getActiveManagerAndAdminUsers();

        $pendingAction->loadMissing(['user', 'project']);

        $this->dispatchToRecipients($recipients, function (User $recipient) use ($pendingAction) {
            return new StaffActionPendingReviewMail($recipient, $pendingAction);
        });
    }

    /**
     * Action 7: Result of the request done by a staff member (approved / rejected).
     * This email is strictly for the Staff user who submitted the request.
     */
    public function sendStaffActionResultEmail(StaffPendingAction $pendingAction, string $decision = 'approved'): void
    {
        $pendingAction->loadMissing(['user', 'project', 'reviewer']);
        $recipient = $pendingAction->user;

        if (!$recipient || empty($recipient->email) || $recipient->trashed()) {
            return;
        }

        // Verify recipient is indeed a staff member (or the submitting non-admin user)
        if ($recipient->hasRole('Staff') || (!$recipient->hasRole('Admin') && !$recipient->hasRole('Manager'))) {
            try {
                Mail::to($recipient->email)->send(new StaffActionResultMail($recipient, $pendingAction, $decision));
                Log::info("Staff action result email sent to {$recipient->email}", [
                    'pending_action_id' => $pendingAction->id,
                    'decision' => $decision,
                ]);
            } catch (\Throwable $e) {
                Log::error("Failed sending staff action result email to {$recipient->email}: {$e->getMessage()}", [
                    'pending_action_id' => $pendingAction->id,
                    'decision' => $decision,
                    'exception' => $e,
                ]);
            }
        }
    }

    /**
     * Action: Project warranty expiring warning (7, 3, or 1 day(s) before expiry).
     * Dispatches notification email across active operational users.
     */
    public function sendWarrantyWarningEmail(Project $project, int $daysRemaining = 1): void
    {
        $recipients = $this->getActiveOperationalUsers();

        $this->dispatchToRecipients($recipients, function (User $recipient) use ($project, $daysRemaining) {
            return new WarrantyWarningMail($recipient, $project, $daysRemaining);
        });
    }

    /**
     * Action: A user successfully changed their password.
     * Dispatches security alert notification email to the affected user.
     */
    public function sendPasswordChangedEmail(User $user, ?string $ipAddress = null): void
    {
        if (empty($user->email) || $user->trashed()) {
            return;
        }

        try {
            Mail::to($user->email)->send(new PasswordChangedMail($user, null, $ipAddress));
        } catch (\Throwable $e) {
            Log::error("Failed sending password changed email notification to {$user->email}: {$e->getMessage()}", [
                'user_id' => $user->id,
                'exception' => $e,
            ]);
        }
    }

    /**
     * Retrieve all active operational users (Admin, Manager, Staff) with valid emails.
     * Automatically filters out soft-deleted and temporarily locked users.
     *
     * @return Collection<int, User>
     */
    public function getActiveOperationalUsers(): Collection
    {
        return User::query()
            ->withoutTrashed()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where(function ($query) {
                $query->whereNull('locked_until')
                    ->orWhere('locked_until', '<=', now());
            })
            ->get();
    }

    /**
     * Retrieve all active users holding Admin or Manager roles with valid emails.
     *
     * @return Collection<int, User>
     */
    public function getActiveManagerAndAdminUsers(): Collection
    {
        return User::query()
            ->withoutTrashed()
            ->whereNotNull('email')
            ->where('email', '!=', '')
            ->where(function ($query) {
                $query->whereNull('locked_until')
                    ->orWhere('locked_until', '<=', now());
            })
            ->where(function ($q) {
                $q->whereHas('roles', function ($rq) {
                    $rq->whereIn('name', ['Admin', 'Manager']);
                })->orWhereIn('job_title', ['Admin', 'Manager', 'Project Manager', 'General Manager', 'Administrator']);
            })
            ->get();
    }

    /**
     * Dispatch mailables to a collection of users with defensive error handling.
     *
     * @param Collection<int, User> $recipients
     * @param callable(User): \Illuminate\Mail\Mailable $mailableFactory
     */
    private function dispatchToRecipients(Collection $recipients, callable $mailableFactory): void
    {
        foreach ($recipients as $recipient) {
            if (empty($recipient->email)) {
                continue;
            }

            try {
                Mail::to($recipient->email)->send($mailableFactory($recipient));
            } catch (\Throwable $e) {
                Log::error("Failed sending email notification to {$recipient->email}: {$e->getMessage()}", [
                    'recipient_id' => $recipient->id,
                    'exception' => $e,
                ]);
            }
        }
    }
}
