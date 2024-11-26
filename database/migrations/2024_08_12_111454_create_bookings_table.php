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
        Schema::create('bookings', function (Blueprint $table) {
            $table->id();
            $table->bigInteger('client_id')->nullable();
            $table->string('client_name');
            $table->string('client_email');
            $table->string('client_phone')->nullable();
            $table->string('meeting_date');
            $table->integer('time_slot');
            $table->string('beverage')->nullable();
            $table->string('number_of_attendees');
            $table->string('meeting_shift')->nullable();
            $table->string('meeting_place')->nullable();
            $table->string('booking_status');
            $table->text('meeting_purpose')->nullable();
            $table->string('language');
            $table->mediumText('description');
            $table->unsignedBigInteger('consultant_id')->nullable();
            $table->foreign('consultant_id')->references('id')->on('teams');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bookings');
    }
};
