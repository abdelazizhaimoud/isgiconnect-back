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
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type'); // Notification class name
            $table->morphs('notifiable'); // Who receives the notification
            $table->text('data'); // Notification data
            $table->string('channel')->default('database'); // database, mail, slack, etc.
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->timestamp('read_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->json('metadata')->nullable(); // Additional notification data
            $table->timestamp('created_at')->nullable();
            $table->timestamp('updated_at')->nullable();

            
            // Indexes
            $table->index('type');
            $table->index('channel');
            $table->index('priority');
            $table->index('read_at');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};