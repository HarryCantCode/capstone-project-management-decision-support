<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (!Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable()->after('name');
            }
            if (!Schema::hasColumn('users', 'middle_name')) {
                $table->string('middle_name')->nullable()->after('first_name');
            }
            if (!Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable()->after('middle_name');
            }
            if (!Schema::hasColumn('users', 'birthdate')) {
                $table->date('birthdate')->nullable()->after('last_name');
            }
        });

        // Safely backfill first_name and last_name for existing users from their existing full 'name'
        $users = DB::table('users')->whereNull('first_name')->get();
        foreach ($users as $user) {
            if (!empty($user->name)) {
                $parts = explode(' ', trim($user->name));
                $firstName = array_shift($parts) ?: 'User';
                $lastName = count($parts) > 0 ? implode(' ', $parts) : $firstName;

                DB::table('users')->where('id', $user->id)->update([
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                ]);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $columnsToDrop = [];
            foreach (['first_name', 'middle_name', 'last_name', 'birthdate'] as $col) {
                if (Schema::hasColumn('users', $col)) {
                    $columnsToDrop[] = $col;
                }
            }
            if (!empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
