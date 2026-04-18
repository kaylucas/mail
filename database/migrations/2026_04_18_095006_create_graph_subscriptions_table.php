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
        Schema::create('graph_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('office365_connection_id')->constrained()->cascadeOnDelete();
            $table->string('subscription_id')->unique();
            $table->string('resource');
            $table->json('change_types');
            $table->string('notification_url');
            $table->string('client_state');
            $table->timestamp('expires_at')->index();
            $table->timestamp('last_renewed_at')->nullable();
            $table->enum('status', ['active', 'expired', 'failed', 'pending'])->default('pending');
            $table->text('failure_reason')->nullable();
            $table->timestamps();

            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('graph_subscriptions');
    }
};
