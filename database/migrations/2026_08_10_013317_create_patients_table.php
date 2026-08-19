<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('psychologist_id')->constrained()->restrictOnDelete();

            $table->string('first_name', 100);
            $table->string('last_name', 100);

            $table->string('phone', 30);
            $table->string('email');
            $table->date('birth_date')->nullable();

            $table->timestamps();

            $table->index(['last_name', 'first_name']);
            $table->index('email');
            $table->index('phone');
            $table->index(['psychologist_id', 'last_name', 'first_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('patients');
    }
};
