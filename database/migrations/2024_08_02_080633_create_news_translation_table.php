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
        Schema::create('news_translation', function (Blueprint $table) {
            $table->id();
            $table->text('field_values');
            $table->text('language');
            $table->unsignedBigInteger('news_id');
            $table->foreign('news_id')->references('id')->on('news')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('news_translation');
        Schema::table('news_translation', function (Blueprint $table) {
            // Drop foreign key and column
            $table->dropForeign(['news_id']);
            $table->dropColumn('news_id');
        });
    }
};
