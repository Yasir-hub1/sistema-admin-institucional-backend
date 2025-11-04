<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class AulaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $aulas = [
            [
                'codigo_aula' => 'A-101',
                'nombre' => 'Aula 101',
                'capacidad' => 30,
                'edificio' => 'A',
                'piso' => '1',
                'tipo' => 'aula',
                'activa' => true
            ],
            [
                'codigo_aula' => 'A-102',
                'nombre' => 'Aula 102',
                'capacidad' => 30,
                'edificio' => 'A',
                'piso' => '1',
                'tipo' => 'aula',
                'activa' => true
            ],
            [
                'codigo_aula' => 'A-201',
                'nombre' => 'Aula 201',
                'capacidad' => 40,
                'edificio' => 'A',
                'piso' => '2',
                'tipo' => 'aula',
                'activa' => true
            ],
            [
                'codigo_aula' => 'L-101',
                'nombre' => 'Laboratorio de Computación 1',
                'capacidad' => 25,
                'edificio' => 'L',
                'piso' => '1',
                'tipo' => 'laboratorio',
                'activa' => true
            ],
            [
                'codigo_aula' => 'L-102',
                'nombre' => 'Laboratorio de Computación 2',
                'capacidad' => 25,
                'edificio' => 'L',
                'piso' => '1',
                'tipo' => 'laboratorio',
                'activa' => true
            ],
            [
                'codigo_aula' => 'AUD-001',
                'nombre' => 'Auditorio Principal',
                'capacidad' => 150,
                'edificio' => 'AUD',
                'piso' => '1',
                'tipo' => 'auditorio',
                'activa' => true
            ]
        ];

        foreach ($aulas as $aula) {
            \App\Models\Aula::create($aula);
        }
    }
}
