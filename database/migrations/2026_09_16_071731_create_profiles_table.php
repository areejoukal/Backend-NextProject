<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->unsignedTinyInteger('age')->nullable();
            $table->string('location', 100)->nullable();
            $table->enum('education', [
                'high_school', 'diploma', 'bachelor', 'master', 'phd', 'other'
            ])->nullable();
            $table->string('field', 150)->nullable();
            $table->enum('experience', [
                'beginner', 'learning', 'some_experience', 'professional', 'experienced'
            ])->nullable();
            $table->text('background')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};