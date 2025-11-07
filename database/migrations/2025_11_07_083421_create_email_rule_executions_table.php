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
        Schema::create('email_rule_executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('ai_provider')->nullable();
            $table->string('ai_model')->nullable();
            $table->text('prompt_sent');
            $table->json('ai_response')->nullable();
            $table->boolean('evaluation_result');
            $table->json('actions_executed')->nullable();
            $table->integer('execution_time_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            // Indexes for performance
            $table->index('email_id');
            $table->index('email_rule_id');
            $table->index('user_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_rule_executions');
    }
};
