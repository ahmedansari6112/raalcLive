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
        Schema::create('terms_conditions', function (Blueprint $table) {
            $table->id();
            $table->text('slug');
            $table->text('translated_value');
            $table->string('heading');
            $table->text('description');
            $table->text('language');
            $table->text('platform');
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
        Schema::dropIfExists('terms_conditions');
        Schema::table('terms_conditions', function (Blueprint $table) {
            // Drop foreign key and column
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');
        });
    }
};
