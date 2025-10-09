<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // El orden es importante: primero Campus, luego Facultades.
        $this->call([
            RoleSeeder::class,
            RequisitoSeeder::class,
            TramiteSeeder::class,
            RequisitoTramiteSeeder::class,
        ]);

        // Si en el futuro creas más seeders, los puedes añadir aquí.
    }
}