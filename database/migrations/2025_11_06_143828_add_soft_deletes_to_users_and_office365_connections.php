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
        // Add soft deletes to users table
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
        });

        // Add soft deletes to office365_connections table
        Schema::table('office365_connections', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove soft deletes from users table
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });

        // Remove soft deletes from office365_connections table
        Schema::table('office365_connections', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
