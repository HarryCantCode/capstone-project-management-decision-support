<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// ─── Scheduled Commands ──────────────────────────────────────────────────────

// Check warranty expiry daily at 8:00 AM — notifies Admin and Manager users
// about projects whose warranty ends tomorrow.
Schedule::command('notifications:check-warranty')->dailyAt('08:00');
