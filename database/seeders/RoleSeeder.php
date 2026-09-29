<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

/**
 * Creates the three application roles.
 *
 * Run order matters: RoleSeeder must run before DemoUserSeeder so
 * roles exist when users are assigned to them.
 */
class RoleSeeder extends Seeder
{
    /**
     * The three roles defined in the system specification.
     * These names are referenced by string throughout Policies,
     * middleware, and Blade conditionals — keep them exactly as-is.
     *
     * @var list<string>
     */
    private const ROLES = [
        'Admin',
        'Manager',
        'Staff',
    ];

    public function run(): void
    {
        foreach (self::ROLES as $roleName) {
            Role::firstOrCreate(
                ['name' => $roleName, 'guard_name' => 'web'],
            );
        }

        $this->command->info('Roles seeded: ' . implode(', ', self::ROLES));
    }
}
