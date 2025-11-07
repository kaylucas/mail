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
        Schema::create('email_labels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->constrained()->cascadeOnDelete();
            $table->string('label_name', 100);
            $table->foreignId('applied_by_rule_id')
                ->nullable()
                ->constrained('email_rules')
                ->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            // Unique constraint to prevent duplicate labels on same email
            $table->unique(['email_id', 'label_name']);
            
            // Index for performance
            $table->index('applied_by_rule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_labels');
    }
};
