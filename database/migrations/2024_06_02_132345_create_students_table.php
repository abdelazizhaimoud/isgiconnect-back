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
        Schema::create('students', function (Blueprint $table) {

            $table->id('studentid');
            $table->string('firstname_fr', 100);
            $table->string('lastname_fr', 100);
            $table->string('firstname_ar', 100);
            $table->string('lastname_ar', 100);
            $table->string('gender', 50)->nullable();
            $table->string('cne', 20)->nullable();
            $table->string('cin', 20)->nullable();
            $table->date('birthdate')->nullable();
            $table->string('email', 100)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('nationality', 100)->nullable();
            $table->string('status', 20)->nullable();
            $table->unsignedBigInteger('userid');
            $table->foreign('userid')->references('id')->on('users');
            $table->timestamps();
            $table->primary('studentid');
            $table->softDeletes();
        });
    }
    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
