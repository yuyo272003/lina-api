<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RequisitoTramiteSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('requisito_tramite')->delete();
        // Constancia de Estudios (ID 1)
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 1, 'idRequisito' => 1], // Nombre
            ['idTramite' => 1, 'idRequisito' => 2], // Matricula
            ['idTramite' => 1, 'idRequisito' => 3], // Periodo
            ['idTramite' => 1, 'idRequisito' => 4], // Semestre
            ['idTramite' => 1, 'idRequisito' => 5], // PE
        ]);
        // Examen Extraordinario (ID 4)
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 4, 'idRequisito' => 1], // Nombre
            ['idTramite' => 4, 'idRequisito' => 2], // Matricula
            ['idTramite' => 4, 'idRequisito' => 5], // PE
            ['idTramite' => 4, 'idRequisito' => 6], // Experiencia educativa
            ['idTramite' => 4, 'idRequisito' => 7], // Grupo
            ['idTramite' => 4, 'idRequisito' => 8], // NRC
            ['idTramite' => 4, 'idRequisito' => 9], // Docente
        ]);
    }
}