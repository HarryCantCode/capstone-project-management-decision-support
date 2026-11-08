<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * CheckWarrantyExpiry — daily scheduled command.
 *
 * Finds projects whose warranty_end_date is in 7 days, 3 days, or 1 day and creates
 * warranty_expiry notifications for all users (Admin, Manager, and Staff).
 *
 * Idempotent: NotificationService checks for existing notifications
 * on the same day for the same project and days_remaining, so running this command
 * multiple times on the same day will not create duplicates.
 */
class CheckWarrantyExpiry extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'notifications:check-warranty';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Notify all users about projects with warranty expiring in 7, 3, or 1 day(s)';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $thresholds = [7, 3, 1];
        $totalNotified = 0;

        foreach ($thresholds as $days) {
            $targetDate = now()->addDays($days)->toDateString();
            $projects = Project::whereDate('warranty_end_date', $targetDate)->get();

            if ($projects->isNotEmpty()) {
                $this->info("Found {$projects->count()} project(s) with warranty expiring in {$days} day(s) ({$targetDate}).");
                foreach ($projects as $project) {
                    $notificationService->notifyWarrantyExpiry($project, $days);
                    $this->line("  → Notified for: {$project->name} ({$project->project_code}) - {$days} day(s) remaining");
                    $totalNotified++;
                }
            }
        }

        if ($totalNotified === 0) {
            $this->info('No projects with warranty expiring in 7, 3, or 1 days.');

            return self::SUCCESS;
        }

        $this->info("Warranty expiry notifications dispatched ({$totalNotified} total).");

        return self::SUCCESS;
    }
}
