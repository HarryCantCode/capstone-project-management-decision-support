<?php

namespace App\Services;

use App\Mail\MaterialLowStockMail;
use App\Mail\PasswordChangedMail;
use App\Mail\ProjectCompletedMail;
use App\Mail\ProjectCreatedMail;
use App\Mail\StaffActionPendingReviewMail;
use App\Mail\StaffActionResultMail;
use App\Mail\TaskAddedMail;
use App\Mail\TaskAssignedMail;
use App\Mail\TaskDueSoonMail;
use App\Mail\WarrantyWarningMail;
use App\Models\Project;
use App\Models\ProjectSubtask;
use App\Models\ProjectTask;
use App\Models\Resource;
use App\Models\StaffPendingAction;
use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Testing\Fakes\MailFake;

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
     * Action: A staff member was assigned to a task.
     * Dispatches notification email directly to the assigned staff member.
     */
    public function sendTaskAssignedEmail(
        Project $project,
        ProjectTask $task,
        ProjectSubtask $subtask,
        User $staffUser,
    ): void {
        if (empty($staffUser->email) || !$this->isDeliverableEmail($staffUser->email)) {
            return;
        }

        $this->deliverSingle(
            $staffUser->email,
            fn () => new TaskAssignedMail($staffUser, $project, $task, $subtask),
            [
                'staff_id' => $staffUser->id,
                'subtask_id' => $subtask->id,
            ],
            "Failed sending task assigned email to {$staffUser->email}",
        );
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
            if (!$this->isDeliverableEmail($recipient->email)) {
                return;
            }

            $this->deliverSingle(
                $recipient->email,
                fn () => new StaffActionResultMail($recipient, $pendingAction, $decision),
                [
                    'pending_action_id' => $pendingAction->id,
                    'decision' => $decision,
                ],
                "Failed sending staff action result email to {$recipient->email}",
            );
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
        if (empty($user->email) || $user->trashed() || !$this->isDeliverableEmail($user->email)) {
            return;
        }

        $this->deliverSingle(
            $user->email,
            fn () => new PasswordChangedMail($user, null, $ipAddress),
            ['user_id' => $user->id],
            "Failed sending password changed email notification to {$user->email}",
        );
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
     * Queues asynchronously after DB transaction commit during live HTTP requests
     * so slow SMTP handshakes never block user actions (such as project creation).
     *
     * @param Collection<int, User> $recipients
     * @param callable(User): Mailable $mailableFactory
     */
    private function dispatchToRecipients(Collection $recipients, callable $mailableFactory): void
    {
        $validRecipients = $recipients->filter(
            fn (User $recipient) => !empty($recipient->email) && $this->isDeliverableEmail($recipient->email),
        )->values();

        if ($validRecipients->isEmpty()) {
            return;
        }

        if ($this->shouldSendSynchronously()) {
            foreach ($validRecipients as $recipient) {
                try {
                    Mail::to($recipient->email)->send($mailableFactory($recipient));
                } catch (\Throwable $e) {
                    Log::error("Failed sending email notification to {$recipient->email}: {$e->getMessage()}", [
                        'recipient_id' => $recipient->id,
                        'exception' => $e,
                    ]);
                }
            }

            return;
        }

        DB::afterCommit(function () use ($validRecipients, $mailableFactory) {
            $queuedAny = false;

            foreach ($validRecipients as $recipient) {
                try {
                    Mail::to($recipient->email)->queue($mailableFactory($recipient));
                    $queuedAny = true;
                } catch (\Throwable $e) {
                    Log::error("Failed queueing email notification to {$recipient->email}: {$e->getMessage()}", [
                        'recipient_id' => $recipient->id,
                        'exception' => $e,
                    ]);
                }
            }

            if ($queuedAny) {
                $this->triggerBackgroundQueueWorker();
            }
        });
    }

    /**
     * Deliver a single mailable (synchronously in tests, queued asynchronously in live requests).
     *
     * @param callable(): Mailable $mailableResolver
     * @param array<string, mixed> $logContext
     */
    private function deliverSingle(
        string $email,
        callable $mailableResolver,
        array $logContext,
        string $errorPrefix,
    ): void {
        if ($this->shouldSendSynchronously()) {
            try {
                Mail::to($email)->send($mailableResolver());
            } catch (\Throwable $e) {
                Log::error("{$errorPrefix}: {$e->getMessage()}", array_merge($logContext, ['exception' => $e]));
            }

            return;
        }

        DB::afterCommit(function () use ($email, $mailableResolver, $logContext, $errorPrefix) {
            try {
                Mail::to($email)->queue($mailableResolver());
                $this->triggerBackgroundQueueWorker();
            } catch (\Throwable $e) {
                Log::error("{$errorPrefix}: {$e->getMessage()}", array_merge($logContext, ['exception' => $e]));
            }
        });
    }

    /**
     * Determine whether emails should be sent synchronously in the current process
     * (automated tests, Mail::fake(), array/log drivers, or sync queue driver).
     */
    private function shouldSendSynchronously(): bool
    {
        if (app()->runningUnitTests()) {
            return true;
        }

        if (Mail::getFacadeRoot() instanceof MailFake) {
            return true;
        }

        if (in_array(config('mail.default'), ['array', 'log'], true)) {
            return true;
        }

        if (config('queue.default') === 'sync') {
            return true;
        }

        return false;
    }

    /**
     * Check whether an email address is routable on external SMTP.
     * In tests or local log/array drivers, all email addresses are allowed.
     */
    private function isDeliverableEmail(string $email): bool
    {
        if ($this->shouldSendSynchronously()) {
            return true;
        }

        $lower = strtolower(trim($email));
        if (
            str_ends_with($lower, '.local')
            || str_ends_with($lower, '.test')
            || str_ends_with($lower, '.invalid')
            || str_ends_with($lower, '@example.com')
        ) {
            return false;
        }

        return true;
    }

    /**
     * Spawn a non-blocking detached background worker to drain queued emails
     * immediately without delaying the HTTP response.
     */
    private function triggerBackgroundQueueWorker(): void
    {
        try {
            $php = escapeshellarg(PHP_BINARY ?: 'php');
            $artisan = escapeshellarg(base_path('artisan'));

            if (DIRECTORY_SEPARATOR === '\\') {
                @pclose(@popen("start /B \"\" {$php} {$artisan} queue:work --stop-when-empty --tries=1 --timeout=30 > NUL 2>&1", 'r'));
            } else {
                @exec("nohup {$php} {$artisan} queue:work --stop-when-empty --tries=1 --timeout=30 > /dev/null 2>&1 &");
            }
        } catch (\Throwable) {
            // Silent catch — background worker spawn is best-effort
        }
    }
}
