<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('psys') && ! Schema::hasTable('psychologists')) {
            Schema::rename('psys', 'psychologists');
        }

        if (Schema::hasTable('psychologists')) {
            return;
        }

        Schema::create('psychologists', function (Blueprint $table) {
            $table->id();

            $table->foreignId('user_id')
                ->unique()
                ->constrained('users')
                ->cascadeOnDelete();

            $table->string('first_name', 100);
            $table->string('last_name', 100);
            $table->string('speciality', 150);
            $table->text('bio')->nullable();
            $table->string('photo')->nullable();
            $table->string('phone', 30)->nullable();
            $table->unsignedSmallInteger('consultation_duration')->default(60);
            $table->decimal('price', 10, 2)->nullable();
            $table->boolean('online_enabled')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('psychologists');
    }
};
