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
        Schema::create('email_reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('reminder_text');
            $table->dateTime('reminder_date');
            $table->enum('status', ['pending', 'triggered', 'dismissed', 'completed'])->default('pending');
            $table->foreignId('applied_by_rule_id')
                ->nullable()
                ->constrained('email_rules')
                ->nullOnDelete();
            
            // Status transition timestamps
            $table->timestamp('triggered_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            
            $table->timestamps();

            // Indexes for queries
            $table->index(['user_id', 'status']);
            $table->index('email_id');
            $table->index(['reminder_date', 'status']); // For scheduled checks
            $table->index('applied_by_rule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_reminders');
    }
};
