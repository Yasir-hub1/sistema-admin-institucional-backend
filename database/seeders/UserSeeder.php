<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminRole = \App\Models\Role::where('nombre', 'admin')->first();

        if ($adminRole) {
            \App\Models\User::create([
                'name' => 'Administrador Sistema FICCT',
                'email' => 'admin@ficct.edu.bo',
                'password' => \Illuminate\Support\Facades\Hash::make('admin123'),
                'rol_id' => $adminRole->id,
                'activo' => true
            ]);
        }
    }
}
