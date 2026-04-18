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
            $table->string('attachment_id');
            $table->string('name');
            $table->string('content_type');
            $table->bigInteger('size')->default(0);
            $table->boolean('is_inline')->default(false);
            $table->string('content_id')->nullable();
            $table->string('content_location')->nullable();
            $table->string('file_path')->nullable();
            $table->timestamps();

            $table->index('email_id');
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
