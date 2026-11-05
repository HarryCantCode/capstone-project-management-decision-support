<?php

namespace App\Console\Commands;

use App\Models\ProjectSubtask;
use App\Models\ProjectTask;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * CheckUpcomingDueTasks — daily scheduled command.
 *
 * Checks for uncompleted specific sub tasks and major tasks that are due within the next 2 days
 * and dispatches notifications for Admin and Manager users.
 *
 * Idempotent: NotificationService prevents duplicate notifications on the same day for the same task.
 */
class CheckUpcomingDueTasks extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:check-due-tasks';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify Admin and Manager users about tasks due within the next 2 days';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $today = now()->startOfDay()->toDateString();
        $inTwoDays = now()->addDays(2)->endOfDay()->toDateString();

        // 1. Check uncompleted sub tasks due within next 2 days
        $dueSubtasks = ProjectSubtask::with(['task.project'])
            ->where('is_completed', false)
            ->whereNotNull('due_date')
            ->whereDate('due_date', '>=', $today)
            ->whereDate('due_date', '<=', $inTwoDays)
            ->get();

        $count = 0;
        foreach ($dueSubtasks as $subtask) {
            $task = $subtask->task;
            $project = $task?->project;
            if ($project && $task && $project->status !== 'completed') {
                $notificationService->notifyUpcomingDueTask($project, $task, $subtask);
                $count++;
            }
        }

        // 2. Check uncompleted major tasks due within next 2 days
        $dueMajorTasks = ProjectTask::with('project')
            ->where('status', '!=', 'completed')
            ->whereNotNull('end_date')
            ->whereDate('end_date', '>=', $today)
            ->whereDate('end_date', '<=', $inTwoDays)
            ->get();

        foreach ($dueMajorTasks as $task) {
            $project = $task->project;
            if ($project && $project->status !== 'completed') {
                $notificationService->notifyUpcomingDueTask($project, $task, null);
                $count++;
            }
        }

        // 3. Check materials running low (< 5 quantity available)
        $lowStockMaterials = \App\Models\Resource::query()
            ->where('quantity_available', '<', 5)
            ->get();

        foreach ($lowStockMaterials as $mat) {
            $notificationService->notifyLowStockMaterial($mat);
        }

        $this->info("Processed upcoming due tasks: dispatched {$count} notification(s).");

        return self::SUCCESS;
    }
}
