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
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('categories')->onDelete('cascade');
            $table->foreignId('content_type_id')->nullable()->constrained()->onDelete('set null'); // Category can be specific to content type
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('color')->nullable(); // For UI theming
            $table->string('icon')->nullable();
            $table->json('meta_data')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->integer('content_count')->default(0); // Cache count
            $table->string('seo_title')->nullable();
            $table->text('seo_description')->nullable();
            $table->integer('lft')->default(0); // For nested set model
            $table->integer('rgt')->default(0); // For nested set model
            $table->integer('depth')->default(0); // Category depth level
            $table->timestamps();
            
            // Indexes
            $table->index('parent_id');
            $table->index('content_type_id');
            $table->index('slug');
            $table->index('is_active');
            $table->index('sort_order');
            $table->index(['lft', 'rgt']);
            $table->index('depth');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};