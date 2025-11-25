<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('email_view_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_view_id')->constrained('email_views')->cascadeOnDelete();
            $table->foreignId('email_rule_id')->constrained('email_rules')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['email_view_id', 'email_rule_id'], 'email_view_rules_unique');
            $table->index('email_rule_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_view_rules');
    }
};
