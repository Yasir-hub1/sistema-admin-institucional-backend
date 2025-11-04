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
        Schema::create('horarios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grupo_id')->constrained('grupos')->onDelete('cascade');
            $table->foreignId('docente_id')->constrained('docentes')->onDelete('cascade');
            $table->foreignId('aula_id')->constrained('aulas')->onDelete('cascade');
            $table->integer('dia_semana'); // 1-6: lunes a sábado
            $table->time('hora_inicio');
            $table->time('hora_fin');
            $table->timestamps();

            // Índices para optimizar consultas
            $table->index(['grupo_id']);
            $table->index(['docente_id']);
            $table->index(['aula_id']);
            $table->index(['dia_semana']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('horarios');
    }
};
