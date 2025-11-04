<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('docentes', function (Blueprint $table) {
            $table->integer('carga_horaria_maxima')->default(40)->after('direccion')->comment('Carga horaria máxima semanal en horas');
            $table->json('disponibilidad_horaria')->nullable()->after('carga_horaria_maxima')->comment('JSON con disponibilidad por día de la semana');
        });
    }

    public function down(): void
    {
        Schema::table('docentes', function (Blueprint $table) {
            $table->dropColumn(['carga_horaria_maxima', 'disponibilidad_horaria']);
        });
    }
};
