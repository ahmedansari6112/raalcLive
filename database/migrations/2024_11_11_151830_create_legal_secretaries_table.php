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
        Schema::create('legal_secretaries', function (Blueprint $table) {
            $table->id();
            $table->string('legal_secretary_image')->nullable();
            $table->string('legal_secretary_email');
            $table->text('qr_code_image')->nullable();
            $table->integer('order_number');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_secretaries');
    }
};
