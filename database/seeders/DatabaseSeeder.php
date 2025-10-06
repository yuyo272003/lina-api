<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Le decimos al seeder principal que ejecute nuestro RoleSeeder
        $this->call([
            RoleSeeder::class,
        ]);

        // Si en el futuro creas más seeders, los puedes añadir aquí.
    }
}