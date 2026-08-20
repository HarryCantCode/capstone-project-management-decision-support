<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * Production guard: demo users are never seeded in production.
     * Run `php artisan db:seed` in local/development only.
     * In production, only RoleSeeder runs (roles must exist; fake users must not).
     */
    public function run(): void
    {
        $this->call(RoleSeeder::class);

        if (app()->environment(['local', 'testing'])) {
            $this->call(DemoUserSeeder::class);
        }
    }
}
