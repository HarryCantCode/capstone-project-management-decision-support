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
            if (!Schema::hasColumn('users', 'account_number')) {
                $table->string('account_number', 7)->nullable()->unique()->after('employee_number');
            }
        });

        // 1. Backfill unique 7-digit account numbers for all existing users (e.g. 1000001, 1000002, ...)
        $users = DB::table('users')->whereNull('account_number')->orderBy('id')->get();
        $startNumber = 1000001;

        // Check if there are already any account numbers in use
        $maxExisting = DB::table('users')->whereNotNull('account_number')->max('account_number');
        if ($maxExisting && is_numeric($maxExisting)) {
            $startNumber = ((int) $maxExisting) + 1;
        }

        foreach ($users as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'account_number' => str_pad((string) $startNumber++, 7, '0', STR_PAD_LEFT),
            ]);
        }

        // 2. Rename role 'Inventory Staff' to 'Staff' in Spatie roles table
        DB::table('roles')->where('name', 'Inventory Staff')->update(['name' => 'Staff']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Revert role rename
        DB::table('roles')->where('name', 'Staff')->update(['name' => 'Inventory Staff']);

        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'account_number')) {
                $table->dropColumn('account_number');
            }
        });
    }
};
