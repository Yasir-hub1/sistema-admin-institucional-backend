<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notificaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->onDelete('cascade');
            $table->string('tipo'); // 'horario_cambio', 'asistencia', 'alerta', 'sistema'
            $table->string('titulo');
            $table->text('mensaje');
            $table->string('accion_url')->nullable();
            $table->boolean('leida')->default(false);
            $table->timestamp('leida_at')->nullable();
            $table->json('datos_adicionales')->nullable(); // Para información extra
            $table->timestamps();

            $table->index(['user_id', 'leida']);
            $table->index('tipo');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notificaciones');
    }
};

