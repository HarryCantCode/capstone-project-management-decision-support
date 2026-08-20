<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Seeds one demo user per role with realistic (non-fake) credentials.
 *
 * These accounts are for local development and the capstone demo only.
 * They must NOT be seeded in production — DatabaseSeeder checks APP_ENV.
 *
 * Credentials are intentionally plain enough to type during a live demo.
 * Change them before any real deployment.
 */
class DemoUserSeeder extends Seeder
{
    public function run(): void
    {
        $users = [
            [
                'name' => 'Harry Reyes',
                'email' => 'admin@dex-pms.local',
                'password' => Hash::make('DexAdmin2026!'),
                'role' => 'Admin',
            ],
            [
                'name' => 'Maria Santos',
                'email' => 'manager@dex-pms.local',
                'password' => Hash::make('DexManager2026!'),
                'role' => 'Manager',
            ],
            [
                'name' => 'Jose Cruz',
                'email' => 'inventory@dex-pms.local',
                'password' => Hash::make('DexInventory2026!'),
                'role' => 'Inventory Staff',
            ],
        ];

        foreach ($users as $userData) {
            $role = $userData['role'];
            unset($userData['role']);

            /** @var User $user */
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                $userData,
            );

            // Track who created the user (self-reference for seeded admin;
            // admin creates the other two roles)
            if (! $user->created_by) {
                $user->created_by = $user->id;
                $user->updated_by = $user->id;
                $user->saveQuietly(); // suppress activity log for seeder
            }

            $user->syncRoles([$role]);

            $this->command->info("Seeded {$role}: {$userData['email']}");
        }
    }
}
