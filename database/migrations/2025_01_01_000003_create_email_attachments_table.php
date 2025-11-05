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
        Schema::create('email_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_id')->constrained()->cascadeOnDelete();
            $table->string('attachment_id')->index();
            $table->string('name');
            $table->string('content_type');
            $table->bigInteger('size');
            $table->boolean('is_inline')->default(false);
            $table->string('content_id')->nullable();
            $table->string('content_location')->nullable();
            $table->binary('content_bytes')->nullable();
            $table->text('download_url')->nullable();
            $table->timestamp('last_modified_date_time')->nullable();
            $table->timestamps();

            // Composite unique index to prevent duplicates
            $table->unique(['email_id', 'attachment_id']);
            // Additional indexes
            $table->index('content_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_attachments');
    }
};
