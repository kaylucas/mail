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
            $table->string('label_name');
            $table->string('color')->nullable()->comment('Hex color code for UI display');
            $table->foreignId('applied_by_rule_id')
                ->nullable()
                ->constrained('email_rules')
                ->nullOnDelete();
            $table->timestamps();

            // Unique constraint: one label per email
            $table->unique(['email_id', 'label_name']);
            
            // Indexes for performance
            $table->index('email_id');
            $table->index('label_name');
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
