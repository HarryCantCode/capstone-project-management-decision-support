<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Migration: Updates personnel table expertise column from restrictive enum to varchar
 * and migrates existing 'Worker' expertise to 'Worker / Installation Personnel'.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('personnel', function (Blueprint $table) {
            $table->string('expertise', 100)->change();
        });

        DB::table('personnel')
            ->where('expertise', 'Worker')
            ->update(['expertise' => 'Worker / Installation Personnel']);
    }

    public function down(): void
    {
        DB::table('personnel')
            ->where('expertise', 'Worker / Installation Personnel')
            ->update(['expertise' => 'Worker']);

        Schema::table('personnel', function (Blueprint $table) {
            $table->enum('expertise', ['Site Engineer', 'Foreman', 'Safety Officer', 'Worker'])->change();
        });
    }
};
