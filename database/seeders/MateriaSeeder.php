<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class MateriaSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $materias = [
            [
                'codigo_materia' => 'INF-101',
                'nombre' => 'Programación I',
                'sigla' => 'PROG1',
                'horas_teoricas' => 3,
                'horas_practicas' => 2,
                'nivel' => 1,
                'semestre' => 1
            ],
            [
                'codigo_materia' => 'INF-102',
                'nombre' => 'Matemáticas Discretas',
                'sigla' => 'MATDIS',
                'horas_teoricas' => 4,
                'horas_practicas' => 0,
                'nivel' => 1,
                'semestre' => 1
            ],
            [
                'codigo_materia' => 'INF-201',
                'nombre' => 'Estructuras de Datos',
                'sigla' => 'ED',
                'horas_teoricas' => 3,
                'horas_practicas' => 2,
                'nivel' => 2,
                'semestre' => 3
            ],
            [
                'codigo_materia' => 'INF-202',
                'nombre' => 'Bases de Datos',
                'sigla' => 'BD',
                'horas_teoricas' => 3,
                'horas_practicas' => 2,
                'nivel' => 2,
                'semestre' => 4
            ],
            [
                'codigo_materia' => 'INF-301',
                'nombre' => 'Ingeniería de Software',
                'sigla' => 'IS',
                'horas_teoricas' => 3,
                'horas_practicas' => 2,
                'nivel' => 3,
                'semestre' => 5
            ],
            [
                'codigo_materia' => 'INF-302',
                'nombre' => 'Redes de Computadoras',
                'sigla' => 'REDES',
                'horas_teoricas' => 3,
                'horas_practicas' => 2,
                'nivel' => 3,
                'semestre' => 6
            ],
            [
                'codigo_materia' => 'INF-401',
                'nombre' => 'Inteligencia Artificial',
                'sigla' => 'IA',
                'horas_teoricas' => 3,
                'horas_practicas' => 2,
                'nivel' => 4,
                'semestre' => 7
            ],
            [
                'codigo_materia' => 'INF-402',
                'nombre' => 'Proyecto de Grado',
                'sigla' => 'PG',
                'horas_teoricas' => 2,
                'horas_practicas' => 4,
                'nivel' => 4,
                'semestre' => 8
            ]
        ];

        foreach ($materias as $materia) {
            \App\Models\Materia::create($materia);
        }
    }
}
