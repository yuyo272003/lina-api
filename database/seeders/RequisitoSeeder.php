<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RequisitoSeeder extends Seeder
{
    public function run(): void
    {
        // Limpiamos la tabla para evitar duplicados al ejecutar el seeder
        DB::table('requisitos')->delete();
        DB::table('requisitos')->insert([
            ['idRequisito' => 1, 'nombreRequisito' => 'Nombre', 'tipo' => 'dato'],
            ['idRequisito' => 2, 'nombreRequisito' => 'Matricula', 'tipo' => 'dato'],
            ['idRequisito' => 3, 'nombreRequisito' => 'Periodo', 'tipo' => 'dato'],
            ['idRequisito' => 4, 'nombreRequisito' => 'Semestre', 'tipo' => 'dato'],
            ['idRequisito' => 5, 'nombreRequisito' => 'Programa Educativo (PE)', 'tipo' => 'dato'],
            ['idRequisito' => 6, 'nombreRequisito' => 'Experiencia educativa', 'tipo' => 'dato'],
            ['idRequisito' => 7, 'nombreRequisito' => 'Grupo', 'tipo' => 'dato'],
            ['idRequisito' => 8, 'nombreRequisito' => 'Nrc de la Experiencia Educativa', 'tipo' => 'dato'],
            ['idRequisito' => 9, 'nombreRequisito' => 'Docente de la Experiencia Educativa', 'tipo' => 'dato'],
            ['idRequisito' => 10, 'nombreRequisito' => 'Correo electrónico institucional', 'tipo' => 'dato'],
        ]);
    }
}