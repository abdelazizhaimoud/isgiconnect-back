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
        Schema::create('media_folders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('media_folders')->onDelete('cascade');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Owner
            $table->string('name');
            $table->string('slug');
            $table->text('description')->nullable();
            $table->json('permissions')->nullable(); // Folder access permissions
            $table->boolean('is_public')->default(false);
            $table->integer('media_count')->default(0); // Cache count
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            // Indexes
            $table->index('parent_id');
            $table->index('user_id');
            $table->index('slug');
            $table->index('is_public');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_folders');
    }
};