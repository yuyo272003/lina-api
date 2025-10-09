<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TramiteSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('tramites')->delete();
        DB::table('tramites')->insert([
            ['idTramite' => 1, 'nombreTramite' => 'Constancia de estudios', 'costoTramite' => 12.00],
            ['idTramite' => 2, 'nombreTramite' => 'Boleta', 'costoTramite' => 12.00],
            ['idTramite' => 3, 'nombreTramite' => 'Cardex', 'costoTramite' => 12.00],
            ['idTramite' => 4, 'nombreTramite' => 'Solicitud de examen Extraordinario', 'costoTramite' => 3.00],
        ]);
    }
}