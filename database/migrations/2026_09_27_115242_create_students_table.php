<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users');
            $table->string('student_number', 20)->unique();
            $table->string('program', 100);
            $table->unsignedTinyInteger('year_level');
            $table->enum('status', ['active', 'on_leave', 'graduated'])->default('active');
            $table->bigInteger('balance_centavos')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
