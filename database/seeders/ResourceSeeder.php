<?php

namespace Database\Seeders;

use App\Models\Resource;
use App\Models\User;
use Illuminate\Database\Seeder;

class ResourceSeeder extends Seeder
{
    public function run(): void
    {
        $manager = User::query()
            ->where('email', 'manager@dex-pms.local')
            ->firstOrFail();

        $resources = [
            ['name' => 'Elevator Guide Rail', 'resource_code' => 'MAT-2026-001', 'type' => 'material', 'quantity_available' => 48, 'condition' => 'good'],
            ['name' => 'Safety Harness', 'resource_code' => 'TOOL-2026-001', 'type' => 'tool', 'quantity_available' => 12, 'condition' => 'good'],
            ['name' => 'Chain Block Hoist', 'resource_code' => 'EQP-2026-001', 'type' => 'equipment', 'quantity_available' => 3, 'condition' => 'needs_maintenance'],
        ];

        foreach ($resources as $resource) {
            Resource::query()->firstOrCreate(
                ['resource_code' => $resource['resource_code']],
                $resource + [
                    'created_by' => $manager->id,
                    'updated_by' => $manager->id,
                ],
            );
        }
    }
}
