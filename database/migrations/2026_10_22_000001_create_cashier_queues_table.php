<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_queues', function (Blueprint $table) {
            $table->id();
            $table->date('queue_date')->index();
            $table->string('queue_number', 20);
            $table->unsignedInteger('sequence_number');
            $table->foreignId('student_id')->constrained('students')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_centavos');
            $table->enum('payment_type', ['tuition', 'misc_fee', 'document_fee']);
            $table->enum('status', ['waiting', 'serving', 'completed', 'skipped', 'cancelled'])->default('waiting');
            $table->boolean('is_present')->default(false);
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('called_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('called_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('or_number', 40)->nullable();
            $table->timestamps();

            $table->unique(['queue_date', 'sequence_number']);
            $table->index(['queue_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_queues');
    }
};
