<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            [
                'nombre' => 'admin',
                'descripcion' => 'Administrador del sistema con acceso completo'
            ],
            [
                'nombre' => 'coordinador',
                'descripcion' => 'Coordinador académico con acceso a gestión de horarios y docentes'
            ],
            [
                'nombre' => 'docente',
                'descripcion' => 'Docente con acceso a sus horarios y registro de asistencias'
            ],
            [
                'nombre' => 'autoridad',
                'descripcion' => 'Autoridad académica con acceso a reportes y estadísticas'
            ]
        ];

        foreach ($roles as $role) {
            \App\Models\Role::create($role);
        }
    }
}
