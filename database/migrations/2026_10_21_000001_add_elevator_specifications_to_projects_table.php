<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds elevator technical specifications to the projects table:
 * - rated_capacity (in kgs)
 * - lifting_speed (in m/min)
 * - no_of_stops (number only)
 * - cage_dimension (mW x mD x mH)
 * - wall_and_ceiling (text)
 * - flooring (text)
 * - door_operation (dropdown / text)
 * - power_source (volts, phase, hz)
 * - operation (how elevator is operated)
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('rated_capacity', 100)->nullable()->after('project_type');
            $table->string('lifting_speed', 100)->nullable()->after('rated_capacity');
            $table->unsignedSmallInteger('no_of_stops')->nullable()->after('lifting_speed');
            $table->string('cage_dimension', 255)->nullable()->after('no_of_stops');
            $table->string('wall_and_ceiling', 255)->nullable()->after('cage_dimension');
            $table->string('flooring', 255)->nullable()->after('wall_and_ceiling');
            $table->string('door_operation', 100)->nullable()->after('flooring');
            $table->string('power_source', 100)->nullable()->after('door_operation');
            $table->string('operation', 100)->nullable()->after('power_source');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn([
                'rated_capacity',
                'lifting_speed',
                'no_of_stops',
                'cage_dimension',
                'wall_and_ceiling',
                'flooring',
                'door_operation',
                'power_source',
                'operation',
            ]);
        });
    }
};
