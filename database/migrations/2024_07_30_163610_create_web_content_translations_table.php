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
        Schema::create('web_content_translations', function (Blueprint $table) {
            $table->id();
            $table->text('translated_value');
            $table->string('language');
            $table->unsignedBigInteger('web_content_id');
            $table->foreign('web_content_id')->references('id')->on('web_content')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('web_content_translations');
    }
};
