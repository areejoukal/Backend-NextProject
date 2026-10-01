<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('next_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('analysis_id')->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('order'); // 1 = primary, 2,3 = secondary
            $table->enum('role', ['primary', 'secondary']);

            $table->string('title', 300);
            $table->text('reason')->nullable();
            $table->text('why')->nullable();
            $table->text('outcome')->nullable();
            $table->json('steps')->nullable(); // string[]
            $table->text('completion_criteria')->nullable();
            $table->enum('effort', ['low', 'medium', 'high'])->nullable();
            $table->string('time_estimate', 100)->nullable();

            $table->enum('status', ['pending', 'in_progress', 'completed'])
                  ->default('pending');
            $table->text('note')->nullable();
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('next_steps');
    }
};