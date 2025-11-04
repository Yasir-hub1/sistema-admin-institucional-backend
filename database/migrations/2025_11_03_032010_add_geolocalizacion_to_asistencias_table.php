<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->decimal('latitud', 10, 8)->nullable()->after('metodo_registro');
            $table->decimal('longitud', 11, 8)->nullable()->after('latitud');
            $table->string('direccion_geolocalizacion')->nullable()->after('longitud');
            $table->decimal('distancia_aula', 8, 2)->nullable()->after('direccion_geolocalizacion')->comment('Distancia en metros desde el aula');
            $table->boolean('geolocalizacion_valida')->default(false)->after('distancia_aula');

            // Cambiar método_registro para incluir geolocalizacion
            $table->dropColumn('metodo_registro');
        });

        Schema::table('asistencias', function (Blueprint $table) {
            $table->enum('metodo_registro', ['formulario', 'qr', 'manual', 'geolocalizacion'])->default('formulario')->after('estado');
        });
    }

    public function down(): void
    {
        Schema::table('asistencias', function (Blueprint $table) {
            $table->dropColumn(['latitud', 'longitud', 'direccion_geolocalizacion', 'distancia_aula', 'geolocalizacion_valida']);
            $table->dropColumn('metodo_registro');
        });

        Schema::table('asistencias', function (Blueprint $table) {
            $table->enum('metodo_registro', ['formulario', 'qr', 'manual'])->default('formulario')->after('estado');
        });
    }
};
