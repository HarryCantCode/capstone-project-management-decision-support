<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('resources', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('resource_code')->unique();
            $table->enum('type', ['material', 'tool', 'equipment'])->index();
            $table->unsignedInteger('quantity_available')->default(0);
            $table->enum('condition', ['good', 'needs_maintenance', 'out_of_service'])->default('good')->index();
            $table->softDeletes();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->constrained('users');
            $table->timestamps();

            $table->index(['type', 'quantity_available']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('resources');
    }
};
