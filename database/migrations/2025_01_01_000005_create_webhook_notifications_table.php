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
        Schema::create('webhook_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('subscription_id')->index();
            $table->enum('change_type', ['created', 'updated', 'deleted']);
            $table->string('resource');
            $table->json('resource_data');
            $table->string('client_state');
            $table->string('tenant_id')->nullable();
            $table->timestamp('processed_at')->nullable()->index();
            $table->integer('processing_attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            // Composite index for retry logic
            $table->index(['processed_at', 'processing_attempts']);
            // Index for cleanup job
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('webhook_notifications');
    }
};
