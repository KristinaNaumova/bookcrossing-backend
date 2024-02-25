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
        Schema::create('ads', function (Blueprint $table) {
            $table->id();
            $table->string('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('book_name');
            $table->string('book_author');
            $table->text('description')->nullable();
            $table->text('comment')->nullable();
            $table->integer('deadline')->nullable();
            $table->enum('status', ['Active', 'InDeal', 'Archived'])->default('Active');
            $table->enum('type', ['Exchange', 'Rent', 'Gift']);
            $table->dateTime('published_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ads');
    }
};
