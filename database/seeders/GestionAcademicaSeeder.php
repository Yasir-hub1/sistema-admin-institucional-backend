<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class GestionAcademicaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $gestiones = [
            [
                'nombre' => '1-2025',
                'año' => 2025,
                'periodo' => 1,
                'fecha_inicio' => '2025-01-15',
                'fecha_fin' => '2025-06-15',
                'activa' => true
            ],
            [
                'nombre' => '2-2024',
                'año' => 2024,
                'periodo' => 2,
                'fecha_inicio' => '2024-08-15',
                'fecha_fin' => '2024-12-15',
                'activa' => false
            ]
        ];

        foreach ($gestiones as $gestion) {
            \App\Models\GestionAcademica::create($gestion);
        }
    }
}
