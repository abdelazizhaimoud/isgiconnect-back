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
        Schema::create('content_types', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('slug')->unique();
            $table->string('description')->nullable();
            $table->string('icon')->nullable();
            $table->json('fields_schema')->nullable(); // Define custom fields for this content type
            $table->json('settings')->nullable(); // Content type specific settings
            $table->boolean('is_active')->default(true);
            $table->boolean('is_hierarchical')->default(false); // Can have parent-child relationships
            $table->boolean('supports_comments')->default(true);
            $table->boolean('supports_media')->default(true);
            $table->boolean('supports_tags')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            // Indexes
            $table->index('name');
            $table->index('slug');
            $table->index('is_active');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('content_types');
    }
};