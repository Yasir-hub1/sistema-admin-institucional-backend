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
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('horario_id')->constrained('horarios')->onDelete('cascade');
            $table->foreignId('docente_id')->constrained('docentes')->onDelete('cascade');
            $table->date('fecha');
            $table->time('hora_registro');
            $table->enum('estado', ['presente', 'ausente', 'tardanza', 'justificado'])->default('presente');
            $table->enum('metodo_registro', ['formulario', 'qr', 'manual'])->default('formulario');
            $table->text('observaciones')->nullable();
            $table->foreignId('registrado_por')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamps();

            // Índices para optimizar consultas
            $table->index(['horario_id']);
            $table->index(['docente_id']);
            $table->index(['fecha']);
            $table->index(['estado']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
