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
        Schema::create('media', function (Blueprint $table) {
            $table->id();
            $table->foreignId('folder_id')->nullable()->constrained('media_folders')->onDelete('set null');
            $table->foreignId('user_id')->constrained()->onDelete('cascade'); // Uploader
            $table->morphs('mediable'); // Polymorphic relation (content, user, etc.) - nullable
            $table->string('name'); // Original filename
            $table->string('file_name'); // Stored filename
            $table->string('mime_type');
            $table->string('extension');
            $table->bigInteger('size'); // File size in bytes
            $table->string('disk')->default('public'); // Storage disk
            $table->string('path'); // File path
            $table->string('url')->nullable(); // Public URL
            $table->text('alt_text')->nullable(); // For accessibility
            $table->text('caption')->nullable();
            $table->text('description')->nullable();
            $table->json('metadata')->nullable(); // EXIF data, dimensions, etc.
            $table->json('conversions')->nullable(); // Thumbnails, optimized versions
            $table->integer('download_count')->default(0);
            $table->boolean('is_public')->default(true);
            $table->string('hash')->nullable(); // File hash for duplicate detection
            $table->timestamps();
            
            // Indexes
            $table->index('folder_id');
            $table->index('user_id');
            $table->index('mime_type');
            $table->index('extension');
            $table->index('is_public');
            $table->index('hash');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media');
    }
};