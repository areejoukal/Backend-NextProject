<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('goal_id')->constrained()->cascadeOnDelete();

            $table->enum('type', ['initial', 'reassessment']);
            $table->enum('status', ['in_progress', 'completed', 'abandoned'])
                  ->default('in_progress');

            $table->unsignedTinyInteger('current_step')->default(1);
            $table->unsignedTinyInteger('max_steps')->default(8);
            $table->json('user_state')->nullable(); // snapshot للحالة

            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessments');
    }
};