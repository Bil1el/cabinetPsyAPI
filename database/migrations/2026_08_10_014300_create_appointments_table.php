<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psychologist_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->restrictOnDelete();
            $table->dateTime('starts_at');
            $table->dateTime('ends_at');
            $table->string('type', 16);
            $table->string('status', 16)->default('pending');
            $table->text('patient_message')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->string('meeting_url')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->dateTime('confirmed_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();
            $table->index(['psychologist_id', 'starts_at']);
            $table->index(['psychologist_id', 'status']);
            $table->index(['patient_id', 'starts_at']);
            $table->index('status');
            $table->index('type');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
