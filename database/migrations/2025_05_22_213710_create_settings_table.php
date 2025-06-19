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
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('type')->default('string'); // string, integer, boolean, json, array
            $table->string('group')->nullable(); // Group settings together
            $table->text('description')->nullable();
            $table->boolean('is_public')->default(false); // Can be accessed by frontend
            $table->boolean('is_editable')->default(true); // Can be modified via admin
            $table->json('validation_rules')->nullable(); // Validation rules for the value
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            
            // Indexes
            $table->index('key');
            $table->index('group');
            $table->index('is_public');
            $table->index('sort_order');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};