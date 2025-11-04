<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DocenteSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $rolDocente = \App\Models\Role::where('nombre', 'docente')->first();

        if (!$rolDocente) {
            return;
        }

        $docentes = [
            [
                'user' => [
                    'name' => 'Juan Carlos Pérez García',
                    'email' => 'juan.perez@ficct.edu.bo',
                    'password' => \Illuminate\Support\Facades\Hash::make('docente123'),
                    'rol_id' => $rolDocente->id,
                    'activo' => true
                ],
                'docente' => [
                    'codigo_docente' => 'DOC001',
                    'especialidad' => 'Programación',
                    'grado_academico' => 'Magister en Ciencias de la Computación',
                    'telefono' => '70123456',
                    'direccion' => 'Av. 16 de Julio #1234'
                ]
            ],
            [
                'user' => [
                    'name' => 'María Elena Rodríguez López',
                    'email' => 'maria.rodriguez@ficct.edu.bo',
                    'password' => \Illuminate\Support\Facades\Hash::make('docente123'),
                    'rol_id' => $rolDocente->id,
                    'activo' => true
                ],
                'docente' => [
                    'codigo_docente' => 'DOC002',
                    'especialidad' => 'Bases de Datos',
                    'grado_academico' => 'Doctor en Ingeniería de Sistemas',
                    'telefono' => '70123457',
                    'direccion' => 'Calle 15 de Abril #5678'
                ]
            ],
            [
                'user' => [
                    'name' => 'Carlos Alberto Mendoza Vargas',
                    'email' => 'carlos.mendoza@ficct.edu.bo',
                    'password' => \Illuminate\Support\Facades\Hash::make('docente123'),
                    'rol_id' => $rolDocente->id,
                    'activo' => true
                ],
                'docente' => [
                    'codigo_docente' => 'DOC003',
                    'especialidad' => 'Redes de Computadoras',
                    'grado_academico' => 'Magister en Telecomunicaciones',
                    'telefono' => '70123458',
                    'direccion' => 'Av. Circunvalación #9012'
                ]
            ],
            [
                'user' => [
                    'name' => 'Ana Patricia Gutiérrez Torres',
                    'email' => 'ana.gutierrez@ficct.edu.bo',
                    'password' => \Illuminate\Support\Facades\Hash::make('docente123'),
                    'rol_id' => $rolDocente->id,
                    'activo' => true
                ],
                'docente' => [
                    'codigo_docente' => 'DOC004',
                    'especialidad' => 'Inteligencia Artificial',
                    'grado_academico' => 'Doctor en Ciencias de la Computación',
                    'telefono' => '70123459',
                    'direccion' => 'Zona Norte, Calle 3 #3456'
                ]
            ],
            [
                'user' => [
                    'name' => 'Roberto Silva Morales',
                    'email' => 'roberto.silva@ficct.edu.bo',
                    'password' => \Illuminate\Support\Facades\Hash::make('docente123'),
                    'rol_id' => $rolDocente->id,
                    'activo' => true
                ],
                'docente' => [
                    'codigo_docente' => 'DOC005',
                    'especialidad' => 'Ingeniería de Software',
                    'grado_academico' => 'Magister en Ingeniería de Software',
                    'telefono' => '70123460',
                    'direccion' => 'Zona Sur, Av. 6 de Agosto #7890'
                ]
            ]
        ];

        foreach ($docentes as $data) {
            $user = \App\Models\User::create($data['user']);
            \App\Models\Docente::create(array_merge($data['docente'], ['user_id' => $user->id]));
        }
    }
}
