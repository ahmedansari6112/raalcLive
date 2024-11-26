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
        Schema::create('gallery_translation', function (Blueprint $table) {
            $table->id();
            $table->text('field_values');
            $table->text('language');
            $table->unsignedBigInteger('gallery_id');
            $table->foreign('gallery_id')->references('id')->on('gallery')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gallery_translation');
        Schema::table('gallery_translation', function (Blueprint $table) {
            // Drop foreign key and column
            $table->dropForeign(['gallery_id']);
            $table->dropColumn('gallery_id');
        });
    }
};
