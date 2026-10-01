<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analyses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->unique()->constrained()->cascadeOnDelete();

            $table->string('headline', 300);
            $table->text('summary');

            $table->json('known');          // string[]
            $table->json('unknown');        // string[]
            $table->json('controllable');   // string[]
            $table->json('uncontrollable'); // string[]

            $table->string('ai_model', 100)->nullable();
            $table->unsignedInteger('ai_tokens')->nullable();
            $table->unsignedInteger('ai_latency_ms')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analyses');
    }
};