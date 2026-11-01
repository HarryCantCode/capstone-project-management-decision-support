<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (!Schema::hasColumn('projects', 'province')) {
                $table->string('province')->nullable()->after('city');
            }
        });

        Schema::table('personnel', function (Blueprint $table) {
            if (!Schema::hasColumn('personnel', 'province')) {
                $table->string('province')->nullable()->after('city');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            if (Schema::hasColumn('projects', 'province')) {
                $table->dropColumn('province');
            }
        });

        Schema::table('personnel', function (Blueprint $table) {
            if (Schema::hasColumn('personnel', 'province')) {
                $table->dropColumn('province');
            }
        });
    }
};

