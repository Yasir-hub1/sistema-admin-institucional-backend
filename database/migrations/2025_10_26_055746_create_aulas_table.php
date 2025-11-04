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
        Schema::create('aulas', function (Blueprint $table) {
            $table->id();
            $table->string('codigo_aula')->unique();
            $table->string('nombre');
            $table->integer('capacidad');
            $table->string('edificio');
            $table->string('piso');
            $table->enum('tipo', ['aula', 'laboratorio', 'auditorio'])->default('aula');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('aulas');
    }
};
