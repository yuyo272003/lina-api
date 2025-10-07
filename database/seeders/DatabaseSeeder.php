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
        ]);
    }
}