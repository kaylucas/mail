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
        Schema::create('emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('office365_connection_id')->constrained()->cascadeOnDelete();
            $table->foreignId('email_folder_id')->constrained()->cascadeOnDelete();
            $table->string('message_id')->index();
            $table->string('internet_message_id')->nullable()->index();
            $table->string('conversation_id')->nullable()->index();
            $table->text('subject')->nullable();
            $table->text('body_preview')->nullable();
            $table->longText('body_content')->nullable();
            $table->enum('body_content_type', ['text', 'html'])->default('html');
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable()->index();
            $table->json('to_recipients')->nullable();
            $table->json('cc_recipients')->nullable();
            $table->json('bcc_recipients')->nullable();
            $table->json('reply_to')->nullable();
            $table->string('sender_name')->nullable();
            $table->string('sender_email')->nullable();
            $table->timestamp('received_date_time')->nullable()->index();
            $table->timestamp('sent_date_time')->nullable()->index();
            $table->boolean('has_attachments')->default(false)->index();
            $table->boolean('is_read')->default(false)->index();
            $table->boolean('is_draft')->default(false);
            $table->enum('importance', ['low', 'normal', 'high'])->default('normal');
            $table->enum('flag_status', ['notFlagged', 'complete', 'flagged'])->default('notFlagged');
            $table->json('categories')->nullable();
            $table->text('web_link')->nullable();
            $table->timestamps();

            // Composite unique index to prevent duplicates per connection (supports future multi-account)
            $table->unique(['office365_connection_id', 'message_id']);
            // Additional indexes
            $table->index('user_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('emails');
    }
};
