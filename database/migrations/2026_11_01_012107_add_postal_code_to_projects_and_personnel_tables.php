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
            $table->string('postal_code', 20)->nullable()->after('city');
        });

        Schema::table('personnel', function (Blueprint $table) {
            $table->string('postal_code', 20)->nullable()->after('city');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('postal_code');
        });

        Schema::table('personnel', function (Blueprint $table) {
            $table->dropColumn('postal_code');
        });
    }
};
