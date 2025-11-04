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
        Schema::create('materias', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_materia')->unique();
            $table->string('nombre');
            $table->string('sigla');
            $table->integer('horas_teoricas')->default(0);
            $table->integer('horas_practicas')->default(0);
            $table->integer('nivel')->default(1);
            $table->integer('semestre')->default(1);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('materias');
    }
};
