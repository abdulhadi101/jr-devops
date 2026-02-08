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
        Schema::create('secrets', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->text('encrypted_content'); // Store encrypted data
            $table->integer('ttl')->nullable(); // Time to live in seconds
            $table->timestamp('expires_at')->nullable(); // Calculated expiration
            $table->timestamps();

            // Index for efficient expiration queries
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('secrets');
    }
};
