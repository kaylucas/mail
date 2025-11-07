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
            $table->foreignId('applied_by_rule_id')
                ->nullable()
                ->constrained('email_rules')
                ->nullOnDelete();
            $table->timestamp('remind_at');
            $table->text('message')->nullable();
            $table->enum('status', ['pending', 'triggered', 'dismissed', 'completed']);
            $table->timestamp('triggered_at')->nullable();
            $table->timestamps();

            // Indexes for performance
            $table->index('user_id');
            $table->index('status');
            $table->index(['remind_at', 'status'], 'idx_remind_at_status');
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
