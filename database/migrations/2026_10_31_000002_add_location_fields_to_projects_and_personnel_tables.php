<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'region')) {
                $table->string('region')->nullable()->after('city');
            }
        });

        Schema::table('personnel', function (Blueprint $table) {
            if (!Schema::hasColumn('personnel', 'barangay')) {
                $table->string('barangay')->nullable()->after('address');
            }
            if (!Schema::hasColumn('personnel', 'city')) {
                $table->string('city')->nullable()->after('barangay');
            }
            if (!Schema::hasColumn('personnel', 'region')) {
                $table->string('region')->nullable()->after('city');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'region')) {
                $table->dropColumn('region');
            }
        });

        Schema::table('personnel', function (Blueprint $table) {
            $cols = [];
            if (Schema::hasColumn('personnel', 'region')) {
                $cols[] = 'region';
            }
            if (Schema::hasColumn('personnel', 'city')) {
                $cols[] = 'city';
            }
            if (Schema::hasColumn('personnel', 'barangay')) {
                $cols[] = 'barangay';
            }
            if (!empty($cols)) {
                $table->dropColumn($cols);
            }
        });
    }
};
