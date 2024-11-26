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
        Schema::create('web_content', function (Blueprint $table) {
            $table->id();
            $table->text('slug');
            $table->text('header_image')->nullable();
            $table->text('sec_two_image')->nullable();
            $table->text('sec_four_image')->nullable();
            $table->text('sec_five_image')->nullable();
            $table->text('sec_para_image')->nullable();
            $table->unsignedBigInteger('created_by');
            $table->foreign('created_by')->references('id')->on('users')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_content');
    }
};
