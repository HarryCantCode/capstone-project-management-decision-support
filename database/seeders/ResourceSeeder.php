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
            ->first() ?? User::query()->first();

        if (!$manager) {
            $manager = User::factory()->create([
                'name' => 'Manager Admin',
                'email' => 'manager@dex-pms.local',
            ]);
        }

        $resources = [
            [
                'name' => 'Electric Wire Rope Hoist',
                'resource_code' => 'DEX-MAT001',
                'type' => 'material',
                'model' => '"NH" model NHHY 2T Cargo lift, complete with overwinding limit switch (made in Japan)',
                'quantity_available' => 4,
                'price' => 546400.00,
                'unit_cost' => 480000.00,
                'demand_status' => 'fast-moving',
                'description' => 'Heavy-duty 2-ton rated electric wire rope hoist assembly engineered for commercial and cargo elevator shaft lifting mechanisms.',
                'condition' => 'good',
            ],
            [
                'name' => 'Cargo Steel Cage Enclosure',
                'resource_code' => 'DEX-MAT002',
                'type' => 'material',
                'model' => 'Steel channel & angle bar frameworks w/ Safety Brake, Guide Shoe & 1 manual panel door',
                'quantity_available' => 3,
                'price' => 454800.00,
                'unit_cost' => 395000.00,
                'demand_status' => 'fast-moving',
                'description' => 'Fabricated structural elevator car cage with anti-fall safety brake mechanism and reinforced guide shoes for 2000kg loads.',
                'condition' => 'good',
            ],
            [
                'name' => 'Electrical Materials & PLC Control System',
                'resource_code' => 'DEX-MAT003',
                'type' => 'material',
                'model' => 'PLC Main Controller, 3 sets Hall Call Buttons, 1 set Limit Switches & wiring loom',
                'quantity_available' => 6,
                'price' => 348500.00,
                'unit_cost' => 295000.00,
                'demand_status' => 'fast-moving',
                'description' => 'Microprocessor PLC control panel with multi-floor dispatch logic, landing push buttons, and traveling limit switches.',
                'condition' => 'good',
            ],
            [
                'name' => 'Self Standing Elevator Support Structure',
                'resource_code' => 'DEX-MAT004',
                'type' => 'material',
                'model' => 'Steel Brackets, Base plates, Safety Spring buffer, Safety fence, Elevator Rail size x 13K w/ Hoist beam & landing sill',
                'quantity_available' => 2,
                'price' => 889245.00,
                'unit_cost' => 775000.00,
                'demand_status' => 'slow-moving',
                'description' => 'Modular free-standing shaft structural framework with heavy machined guide rails (T13K) and pit buffer springs.',
                'condition' => 'good',
            ],
            [
                'name' => 'Landing Doors Set',
                'resource_code' => 'DEX-MAT005',
                'type' => 'material',
                'model' => 'Manual Operated Side Sliding Accordion door complete with Mechanical Interlock',
                'quantity_available' => 8,
                'price' => 382400.00,
                'unit_cost' => 320000.00,
                'demand_status' => 'fast-moving',
                'description' => 'Fire-resistant folding accordion landing entrance doors with interlocked safety door contacts and door-lock mechanisms.',
                'condition' => 'good',
            ],
            [
                'name' => 'Overhead Geared Traction Machine',
                'resource_code' => 'DEX-MAT006',
                'type' => 'material',
                'model' => 'VVVF 5.5kW, 1.0 m/s, 400V 3-Phase, Dual Disc Electromagnetic Brake',
                'quantity_available' => 2,
                'price' => 625000.00,
                'unit_cost' => 540000.00,
                'demand_status' => 'slow-moving',
                'description' => 'Geared traction machine for passenger/freight duty with high-efficiency worm gearbox and VVVF inverter drive compatibility.',
                'condition' => 'good',
            ],
            [
                'name' => 'Elevator Counterweight Block Set',
                'resource_code' => 'DEX-MAT007',
                'type' => 'material',
                'model' => 'Cast iron modular weights (50kg per slab) with counterweight frame',
                'quantity_available' => 24,
                'price' => 185000.00,
                'unit_cost' => 140000.00,
                'demand_status' => 'fast-moving',
                'description' => 'Standard cast iron counterweight balance blocks engineered for shaft balancing and energy dissipation.',
                'condition' => 'good',
            ],
        ];

        foreach ($resources as $resource) {
            Resource::query()->updateOrCreate(
                ['resource_code' => $resource['resource_code']],
                $resource + [
                    'created_by' => $manager->id,
                    'updated_by' => $manager->id,
                ],
            );
        }
    }
}
