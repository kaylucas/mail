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
            $table->foreignId('email_rule_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            
            // AI evaluation details
            $table->string('ai_provider')->nullable()->comment('Which provider was used');
            $table->string('ai_model')->nullable()->comment('Which model was used');
            $table->text('prompt_sent')->nullable()->comment('Full prompt sent to AI');
            $table->json('ai_response')->nullable()->comment('Raw AI response');
            
            // Execution results
            $table->boolean('evaluation_result')->default(false)->comment('Did rule match?');
            $table->integer('actions_executed')->default(0)->comment('Count of successful actions');
            $table->json('actions_taken')->nullable()->comment('Details of actions executed');
            $table->text('error_message')->nullable()->comment('Error if execution failed');
            $table->integer('execution_time_ms')->nullable()->comment('Performance tracking');
            
            $table->timestamps();

            // Indexes for analytics and debugging
            $table->index(['email_rule_id', 'created_at']);
            $table->index('email_id');
            $table->index(['user_id', 'created_at']);
            $table->index('evaluation_result');
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
