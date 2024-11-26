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
        Schema::create('legal_secretaries_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('legal_secretary_id')->constrained('legal_secretaries')->onDelete('cascade');
            $table->string('lang');
            $table->longText('fields_value');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('legal_secretaries_translations');
    }
};
