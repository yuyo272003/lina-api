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
            ['idTramite' => 2, 'nombreTramite' => 'Constancia con avance acrediticio', 'costoTramite' => 12.00],
            ['idTramite' => 3, 'nombreTramite' => 'Boleta', 'costoTramite' => 12.00],
            ['idTramite' => 4, 'nombreTramite' => 'Carta de presentación de Servicio Social', 'costoTramite' => 12.00],
            ['idTramite' => 5, 'nombreTramite' => 'Carta de presentación para Practicas Profesionales', 'costoTramite' => 12.00],
            ['idTramite' => 6, 'nombreTramite' => 'Constancia para el IMSS', 'costoTramite' => 12.00],
            ['idTramite' => 7, 'nombreTramite' => 'Constancia para PEMEX', 'costoTramite' => 12.00],
            ['idTramite' => 8, 'nombreTramite' => 'Cardex', 'costoTramite' => 12.00],
            ['idTramite' => 9, 'nombreTramite' => 'Baja Temporal', 'costoTramite' => 15.00],
            ['idTramite' => 10, 'nombreTramite' => 'Certificación de documentos', 'costoTramite' => 15.00],
            ['idTramite' => 11, 'nombreTramite' => 'Baja Definitiva', 'costoTramite' => 15.00], 
            ['idTramite' => 12, 'nombreTramite' => 'Solicitud de examen Extraordinario', 'costoTramite' => 3.00],
            ['idTramite' => 13, 'nombreTramite' => 'Solicitud de examen de Titulo', 'costoTramite' => 6.00],
            ['idTramite' => 14, 'nombreTramite' => 'Solicitud de examen UO', 'costoTramite' => 12.00],
            ['idTramite' => 15, 'nombreTramite' => 'Constancia tramite de titulación', 'costoTramite' => 12.00],
        ]);
    }
}