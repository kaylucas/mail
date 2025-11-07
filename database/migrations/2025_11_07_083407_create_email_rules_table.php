<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->text('prompt');
            $table->json('simple_conditions')->nullable()->comment('Pre-filter conditions to reduce AI calls');
            $table->boolean('is_active')->default(true);
            $table->integer('priority')->default(0)->comment('Lower number = higher priority');
            $table->string('ai_provider')->nullable()->comment('anthropic, openai, or gemini');
            $table->string('ai_model')->nullable()->comment('Specific model to use');
            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('is_active');
            $table->index(['user_id', 'priority', 'is_active'], 'idx_user_priority_active');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_rules');
    }
};
