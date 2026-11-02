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
        Schema::table('resources', function (Blueprint $table) {
            $table->string('model')->nullable()->after('name');
            $table->decimal('price', 15, 2)->default(0)->after('quantity_available');
            $table->decimal('unit_cost', 15, 2)->default(0)->after('price');
            $table->enum('demand_status', ['fast-moving', 'slow-moving'])->default('fast-moving')->index()->after('unit_cost');
            $table->text('description')->nullable()->after('demand_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('resources', function (Blueprint $table) {
            $table->dropColumn(['model', 'price', 'unit_cost', 'demand_status', 'description']);
        });
    }
};
