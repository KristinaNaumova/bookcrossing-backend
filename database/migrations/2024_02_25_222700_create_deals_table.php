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
        Schema::create('deals', function (Blueprint $table) {
            $table->id();
            $table->string('proposed_book')->nullable();
            $table->enum('deal_status', ['DealWaiting','RefundWaiting', 'Finished']);
            $table->unsignedBigInteger('ad_id');
            $table->foreign('ad_id')->references('id')->on('ads')->cascadeOnDelete();
            $table->string('first_member_id');
            $table->foreign('first_member_id')->references('id')->on('users')->cascadeOnDelete();
            $table->string('second_member_id');
            $table->foreign('second_member_id')->references('id')->on('users')->cascadeOnDelete();
            $table->dateTime('deal_waiting_start_time')->nullable();
            $table->dateTime('deal_waiting_end_time')->nullable();
            $table->dateTime('refund_waiting_start_time')->nullable();
            $table->dateTime('refund_waiting_end_time')->nullable();
            $table->integer('first_member_evaluation')->nullable();
            $table->integer('second_member_evaluation')->nullable();
            $table->string('book_name');
            $table->string('book_author');
            $table->enum('type', ['Exchange', 'Gift', 'Rent']);
            $table->dateTime('deadline')->nullable();
            $table->string('code')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('deals');
    }
};
