<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB; // <-- ¡LA LÍNEA QUE FALTABA!

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Usamos 'roles' en minúscula para coincidir con la migración
        DB::table('roles')->insert([
            ['NombreRole' => 'Admin'],         // ID 1
            ['NombreRole' => 'Academico'],      // ID 2
            ['NombreRole' => 'Estudiante'],     // ID 3
            ['NombreRole' => 'Egresado'],       // ID 4
        ]);
    }
}
