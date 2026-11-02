<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\NotificationService;
use Illuminate\Console\Command;

/**
 * CheckWarrantyExpiry — daily scheduled command.
 *
 * Finds projects whose warranty_end_date is tomorrow and creates
 * warranty_expiry notifications for all Admin and Manager users.
 *
 * Idempotent: NotificationService checks for existing notifications
 * on the same day for the same project, so running this command
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
    protected $description = 'Notify Admin and Manager users about projects with warranty expiring tomorrow';

    /**
     * Execute the console command.
     */
    public function handle(NotificationService $notificationService): int
    {
        $tomorrow = now()->addDay()->toDateString();

        $projects = Project::whereDate('warranty_end_date', $tomorrow)->get();

        if ($projects->isEmpty()) {
            $this->info('No projects with warranty expiring tomorrow.');

            return self::SUCCESS;
        }

        $this->info("Found {$projects->count()} project(s) with warranty expiring on {$tomorrow}.");

        foreach ($projects as $project) {
            $notificationService->notifyWarrantyExpiry($project);
            $this->line("  → Notified for: {$project->name} ({$project->project_code})");
        }

        $this->info('Warranty expiry notifications dispatched.');

        return self::SUCCESS;
    }
}
