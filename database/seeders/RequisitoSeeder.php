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
            ['idRequisito' => 11, 'nombreRequisito' => 'Número de créditos del PE', 'tipo' => 'dato'],
            ['idRequisito' => 12, 'nombreRequisito' => 'Número de créditos del estudiante (hasta el periodo concluido)', 'tipo' => 'dato'],
            ['idRequisito' => 13, 'nombreRequisito' => 'Avance crediticio (hasta el periodo concluido)', 'tipo' => 'dato'],
            ['idRequisito' => 14, 'nombreRequisito' => 'Número de horas del Servicio Social', 'tipo' => 'dato'],
            ['idRequisito' => 15, 'nombreRequisito' => 'Fecha de inicio del Servicio Social', 'tipo' => 'dato'],
            ['idRequisito' => 16, 'nombreRequisito' => 'Nombre completo de la empresa', 'tipo' => 'dato'],
            ['idRequisito' => 17, 'nombreRequisito' => 'Datos de la empresa (Dirección: avenida, número, colonia, código postal, municipio, estado)', 'tipo' => 'dato'],
            ['idRequisito' => 18, 'nombreRequisito' => 'Correo electronico (empresa)', 'tipo' => 'dato'],
            ['idRequisito' => 19, 'nombreRequisito' => 'Atención a quien se le dirige el escrito (grado, nombre completo y cargo)', 'tipo' => 'dato'],
            ['idRequisito' => 20, 'nombreRequisito' => 'Fecha de solicitud', 'tipo' => 'dato'],
            ['idRequisito' => 21, 'nombreRequisito' => 'Aula del Examen', 'tipo' => 'dato'],
            ['idRequisito' => 22, 'nombreRequisito' => 'Fecha de examen', 'tipo' => 'dato'],
            ['idRequisito' => 23, 'nombreRequisito' => 'Hora de examen', 'tipo' => 'dato'],
            ['idRequisito' => 24, 'nombreRequisito' => 'Nombre del docente con el que se cursó en primera inscripción', 'tipo' => 'dato'],
            ['idRequisito' => 25, 'nombreRequisito' => 'Nombre del docente con el que se cursó en segunda inscripción', 'tipo' => 'dato'],
            ['idRequisito' => 26, 'nombreRequisito' => 'Indicación de si se desea excluir a un docente que impartió previamente la EE.', 'tipo' => 'dato'],
            ['idRequisito' => 27, 'nombreRequisito' => 'Correo electronico alterno', 'tipo' => 'dato'],
            ['idRequisito' => 28, 'nombreRequisito' => 'Número de contacto', 'tipo' => 'dato'],
        ]);
    }
}
