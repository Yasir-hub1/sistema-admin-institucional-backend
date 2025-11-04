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
        Schema::create('gestiones_academicas', function (Blueprint $table) {
            $table->id();
            $table->string('nombre'); // Ejemplo: "2-2025"
            $table->integer('año');
            $table->integer('periodo'); // 1 o 2
            $table->date('fecha_inicio');
            $table->date('fecha_fin');
            $table->boolean('activa')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gestiones_academicas');
    }
};
