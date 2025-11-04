<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('aulas', function (Blueprint $table) {
            $table->decimal('latitud', 10, 8)->nullable()->after('capacidad');
            $table->decimal('longitud', 11, 8)->nullable()->after('latitud');
        });
    }

    public function down(): void
    {
        Schema::table('aulas', function (Blueprint $table) {
            $table->dropColumn(['latitud', 'longitud']);
        });
    }
};
