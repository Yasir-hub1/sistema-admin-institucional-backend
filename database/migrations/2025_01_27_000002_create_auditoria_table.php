<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('set null');
            $table->string('accion'); // 'create', 'update', 'delete', 'view', 'export', 'import'
            $table->string('modelo'); // 'Docente', 'Horario', 'Asistencia', etc.
            $table->unsignedBigInteger('modelo_id')->nullable(); // ID del registro afectado
            $table->text('descripcion');
            $table->json('datos_anteriores')->nullable();
            $table->json('datos_nuevos')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
            $table->index(['modelo', 'modelo_id']);
            $table->index('accion');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('auditoria');
    }
};

