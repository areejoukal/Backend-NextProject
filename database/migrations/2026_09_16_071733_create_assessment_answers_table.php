<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessment_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assessment_id')->constrained()->cascadeOnDelete();

            $table->string('question_id', 100);
            $table->string('question_text', 500);
            $table->json('answer'); // الإجابة (قد تكون string أو array)
            $table->decimal('info_gain', 3, 2)->nullable(); // 0.00-1.00
            $table->string('reason', 500)->nullable(); // لماذا سألنا هذا السؤال
            $table->unsignedTinyInteger('order');

            $table->timestamps();

            $table->unique(['assessment_id', 'question_id']);
            $table->index(['assessment_id', 'order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_answers');
    }
};