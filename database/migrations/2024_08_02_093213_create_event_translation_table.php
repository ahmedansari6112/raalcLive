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
        Schema::create('event_translation', function (Blueprint $table) {
            $table->id();
            $table->text('field_values');
            $table->text('language');
            $table->unsignedBigInteger('event_id');
            $table->foreign('event_id')->references('id')->on('event')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_translation');
        Schema::table('event_translation', function (Blueprint $table) {
            // Drop foreign key and column
            $table->dropForeign(['event_id']);
            $table->dropColumn('event_id');
        });
    }
};
