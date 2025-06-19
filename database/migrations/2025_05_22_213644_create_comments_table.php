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
        Schema::create('comments', function (Blueprint $table) {
            $table->id();
            $table->morphs('commentable'); // Polymorphic relation (content, media, etc.)
            $table->foreignId('user_id')->nullable()->constrained()->onDelete('set null');
            $table->foreignId('parent_id')->nullable()->constrained('comments')->onDelete('cascade'); // For threaded comments
            $table->string('author_name')->nullable(); // For guest comments
            $table->string('author_email')->nullable(); // For guest comments
            $table->text('content');
            $table->enum('status', ['pending', 'approved', 'rejected', 'spam'])->default('pending');
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->integer('like_count')->default(0);
            $table->integer('dislike_count')->default(0);
            $table->json('meta_data')->nullable();
            $table->timestamps();
            
            // Indexes
            $table->index('user_id');
            $table->index('parent_id');
            $table->index('status');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('comments');
    }
};