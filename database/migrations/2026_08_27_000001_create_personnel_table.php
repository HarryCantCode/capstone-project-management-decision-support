<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Creates the personnel table for the Manpower Management module.
 *
 * Personnel are field workers tracked for project assignment — they are
 * NOT system login accounts (those live in the users table).
 *
 * employee_id is auto-generated in the format EMP-2026XXX by the model.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::create('personnel', function (Blueprint $table) {
            $table->id();
            $table->string('employee_id')->unique();
            $table->string('first_name');
            $table->string('middle_name')->nullable();
            $table->string('last_name');
            $table->date('date_of_birth');
            $table->text('address');
            $table->enum('expertise', ['Site Engineer', 'Foreman', 'Safety Officer', 'Worker']);

            // Project assignment — nullable means "Not Assigned"
            $table->foreignId('project_id')->nullable()->constrained('projects')->nullOnDelete();
            $table->date('date_assigned')->nullable();

            // Audit columns
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('personnel');
    }
};
