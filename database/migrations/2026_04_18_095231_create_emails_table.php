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
            $table->foreignId('email_folder_id')->nullable()->constrained()->nullOnDelete();
            $table->string('message_id')->unique();
            $table->string('subject')->nullable();
            $table->string('from_name')->nullable();
            $table->string('from_email')->nullable();
            $table->json('to_recipients')->nullable();
            $table->json('cc_recipients')->nullable();
            $table->json('bcc_recipients')->nullable();
            $table->text('body_preview')->nullable();
            $table->longText('body_content')->nullable();
            $table->string('body_content_type')->default('html');
            $table->boolean('is_read')->default(false);
            $table->boolean('is_draft')->default(false);
            $table->enum('importance', ['low', 'normal', 'high'])->default('normal');
            $table->timestamp('received_date_time')->nullable()->index();
            $table->timestamp('sent_date_time')->nullable();
            $table->boolean('has_attachments')->default(false);
            $table->string('internet_message_id')->nullable();
            $table->string('conversation_id')->nullable()->index();
            $table->timestamps();

            $table->index(['user_id', 'received_date_time']);
            $table->index(['user_id', 'is_read']);
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
