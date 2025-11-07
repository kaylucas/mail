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
        Schema::create('email_rule_actions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_rule_id')->constrained()->cascadeOnDelete();
            $table->enum('action_type', ['add_label', 'forward', 'add_reminder']);
            $table->json('action_config')->comment('Configuration specific to action type');
            $table->timestamps();

            // Index for performance
            $table->index('email_rule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_rule_actions');
    }
};
