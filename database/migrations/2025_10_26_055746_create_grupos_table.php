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
        Schema::create('grupos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('materia_id')->constrained('materias')->onDelete('cascade');
            $table->foreignId('gestion_id')->constrained('gestiones_academicas')->onDelete('cascade');
            $table->integer('numero_grupo');
            $table->integer('cupo_maximo')->default(30);
            $table->timestamps();

            // Índice único para evitar duplicados
            $table->unique(['materia_id', 'gestion_id', 'numero_grupo']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('grupos');
    }
};
