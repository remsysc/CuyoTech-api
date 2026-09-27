<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clearances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('students');
            $table->foreignId('department_id')->constrained('departments');
            $table->string('school_year', 9);
            $table->unsignedTinyInteger('semester');
            $table->enum('status', ['pending', 'approved', 'denied'])->default('pending');
            $table->string('remarks', 255)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users');
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'department_id', 'school_year', 'semester'], 'clearance_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clearances');
    }
};
