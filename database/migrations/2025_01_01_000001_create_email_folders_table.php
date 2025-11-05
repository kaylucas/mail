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
        Schema::create('email_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('office365_connection_id')->constrained()->cascadeOnDelete();
            $table->string('folder_id')->index();
            $table->string('parent_folder_id')->nullable()->index();
            $table->string('display_name');
            $table->integer('total_item_count')->default(0);
            $table->integer('unread_item_count')->default(0);
            $table->integer('child_folder_count')->default(0);
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();

            // Composite unique index to prevent duplicates
            $table->unique(['user_id', 'folder_id']);
            // Additional indexes
            $table->index('office365_connection_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('email_folders');
    }
};
