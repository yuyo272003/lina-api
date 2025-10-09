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
        // Constancia con avance acrediticio (ID 2)
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 2, 'idRequisito' => 1], // Nombre
            ['idTramite' => 2, 'idRequisito' => 2], // Matricula
            ['idTramite' => 2, 'idRequisito' => 3], // Periodo
            ['idTramite' => 2, 'idRequisito' => 4], // Semestre
            ['idTramite' => 2, 'idRequisito' => 5], // PE
        ]);

        // Boleta (ID 3)
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 3, 'idRequisito' => 1], // Nombre
            ['idTramite' => 3, 'idRequisito' => 2], // Matricula
            ['idTramite' => 3, 'idRequisito' => 3], // Periodo
            ['idTramite' => 3, 'idRequisito' => 4], // Semestre
            ['idTramite' => 3, 'idRequisito' => 5], // PE
        ]);
        // Carta de Presentación de Servicio Social (ID 4)
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 4, 'idRequisito' => 1], // Nombre
            ['idTramite' => 4, 'idRequisito' => 2], // Matricula
            ['idTramite' => 4, 'idRequisito' => 3], // Periodo
            ['idTramite' => 4, 'idRequisito' => 4], // Semestre
            ['idTramite' => 4, 'idRequisito' => 5], // PE
            ['idTramite' => 4, 'idRequisito' => 11], //Número de créditos del PE
            ['idTramite' => 4, 'idRequisito' => 12], //Número de créditos del estudiante (hasta el periodo concluido)
            ['idTramite' => 4, 'idRequisito' => 13], //Avance crediticio (hasta el periodo concluido)
            ['idTramite' => 4, 'idRequisito' => 14], //Número de horas del Servicio Social
            ['idTramite' => 4, 'idRequisito' => 15], //Fecha de inicio del Servicio Social
            ['idTramite' => 4, 'idRequisito' => 16], //Nombre completo de la empresa
            ['idTramite' => 4, 'idRequisito' => 17], //Datos de la empresa (Dirección: avenida, número, colonia, código postal, municipio, estado)
            ['idTramite' => 4, 'idRequisito' => 18], //Correo electronico (empresa)
            ['idTramite' => 4, 'idRequisito' => 19], //Atención a quien se le dirige el escrito (grado, nombre completo y cargo)
            ['idTramite' => 4, 'idRequisito' => 20], //Fecha de solicitud

        ]);
        // Carta de presentación para Practicas Profesionales (ID 5)
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 5, 'idRequisito' => 1], // Nombre
            ['idTramite' => 5, 'idRequisito' => 2], // Matricula
            ['idTramite' => 5, 'idRequisito' => 3], // Periodo
            ['idTramite' => 5, 'idRequisito' => 4], // Semestre
            ['idTramite' => 5, 'idRequisito' => 5], // PE
            ['idTramite' => 5, 'idRequisito' => 11], //Número de créditos del PE
            ['idTramite' => 5, 'idRequisito' => 12], //Número de créditos del estudiante (hasta el periodo concluido)
            ['idTramite' => 5, 'idRequisito' => 13], //Avance crediticio (hasta el periodo concluido)
            ['idTramite' => 5, 'idRequisito' => 14], //Número de horas del Servicio Social
            ['idTramite' => 5, 'idRequisito' => 15], //Fecha de inicio del Servicio Social
            ['idTramite' => 5, 'idRequisito' => 16], //Nombre completo de la empresa
            ['idTramite' => 5, 'idRequisito' => 17], //Datos de la empresa (Dirección: avenida, número, colonia, código postal, municipio, estado)
            ['idTramite' => 5, 'idRequisito' => 18], //Correo electronico (empresa)
            ['idTramite' => 5, 'idRequisito' => 19], //Atención a quien se le dirige el escrito (grado, nombre completo y cargo)
            ['idTramite' => 5, 'idRequisito' => 20], //Fecha de solicitud

        ]);

        // Constancia para IMSS (ID 6)
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 6, 'idRequisito' => 1], // Nombre
            ['idTramite' => 6, 'idRequisito' => 2], // Matricula
            ['idTramite' => 6, 'idRequisito' => 3], // Periodo
            ['idTramite' => 6, 'idRequisito' => 4], // Semestre
            ['idTramite' => 6, 'idRequisito' => 5], // PE
        ]);

        // Constancia para PEMEX (ID 7)
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 7, 'idRequisito' => 1], // Nombre
            ['idTramite' => 7, 'idRequisito' => 2], // Matricula
            ['idTramite' => 7, 'idRequisito' => 3], // Periodo
            ['idTramite' => 7, 'idRequisito' => 4], // Semestre
            ['idTramite' => 7, 'idRequisito' => 5], // PE
        ]);

        // Cardex (ID 8)
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 8, 'idRequisito' => 1], // Nombre
            ['idTramite' => 8, 'idRequisito' => 2], // Matricula
            ['idTramite' => 8, 'idRequisito' => 3], // Periodo
            ['idTramite' => 8, 'idRequisito' => 4], // Semestre
            ['idTramite' => 8, 'idRequisito' => 5], // PE
        ]);

        // Baja Temporal (ID 9)
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 9, 'idRequisito' => 1], // Nombre
            ['idTramite' => 9, 'idRequisito' => 2], // Matricula
            ['idTramite' => 9, 'idRequisito' => 3], // Periodo
            ['idTramite' => 9, 'idRequisito' => 4], // Semestre
            ['idTramite' => 9, 'idRequisito' => 5], // PE
        ]);

        // Certificación de documentos (ID 10)
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 10, 'idRequisito' => 1], // Nombre
            ['idTramite' => 10, 'idRequisito' => 2], // Matricula
            ['idTramite' => 10, 'idRequisito' => 3], // Periodo
            ['idTramite' => 10, 'idRequisito' => 4], // Semestre
            ['idTramite' => 10, 'idRequisito' => 5], // PE
        ]);

        // Baja definitiva (ID 11)
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 11, 'idRequisito' => 1], // Nombre
            ['idTramite' => 11, 'idRequisito' => 2], // Matricula
            ['idTramite' => 11, 'idRequisito' => 3], // Periodo
            ['idTramite' => 11, 'idRequisito' => 4], // Semestre
            ['idTramite' => 11, 'idRequisito' => 5], // PE
        ]);


        // Examen Extraordinario (ID 12)
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 12, 'idRequisito' => 1], // Nombre
            ['idTramite' => 12, 'idRequisito' => 2], // Matricula
            ['idTramite' => 12, 'idRequisito' => 3], //Periodo
            ['idTramite' => 12, 'idRequisito' => 4], //Semestre
            ['idTramite' => 12, 'idRequisito' => 5], // PE
            ['idTramite' => 12, 'idRequisito' => 6], // Experiencia educativa
            ['idTramite' => 12, 'idRequisito' => 7], // Grupo
            ['idTramite' => 12, 'idRequisito' => 8], // NRC
            ['idTramite' => 12, 'idRequisito' => 9], // Docente
            ['idTramite' => 12, 'idRequisito' => 21], // Aula
            ['idTramite' => 12, 'idRequisito' => 22], // Fecha
            ['idTramite' => 12, 'idRequisito' => 23], // Hora

        ]);

        // Examen Titulo (ID 13)
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 13, 'idRequisito' => 1], // Nombre
            ['idTramite' => 13, 'idRequisito' => 2], // Matricula
            ['idTramite' => 13, 'idRequisito' => 3], //Periodo
            ['idTramite' => 13, 'idRequisito' => 4], //Semestre
            ['idTramite' => 13, 'idRequisito' => 5], // PE
            ['idTramite' => 13, 'idRequisito' => 6], // Experiencia educativa
            ['idTramite' => 13, 'idRequisito' => 7], // Grupo
            ['idTramite' => 13, 'idRequisito' => 8], // NRC
            ['idTramite' => 13, 'idRequisito' => 9], // Docente
            ['idTramite' => 13, 'idRequisito' => 21], // Aula
            ['idTramite' => 13, 'idRequisito' => 22], // Fecha
            ['idTramite' => 13, 'idRequisito' => 23], // Hora
        ]);

        // Examen UO (ID 14)
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 14, 'idRequisito' => 1], // Nombre
            ['idTramite' => 14, 'idRequisito' => 2], // Matricula
            ['idTramite' => 14, 'idRequisito' => 3], //Periodo
            ['idTramite' => 14, 'idRequisito' => 5], // PE
            ['idTramite' => 14, 'idRequisito' => 6], // Experiencia educativa
            ['idTramite' => 14, 'idRequisito' => 10], //Correo electronico institucional
            ['idTramite' => 14, 'idRequisito' => 24], //Nombre del docente con el que se cursó en primera inscripción
            ['idTramite' => 14, 'idRequisito' => 25], //Nombre del docente con el que se cursó en segunda inscripción
            ['idTramite' => 14, 'idRequisito' => 26], //Indicación de si se desea excluir a un docente que impartió previamente la EE.
            ['idTramite' => 14, 'idRequisito' => 27], //Correo electronico alterno
            ['idTramite' => 14, 'idRequisito' => 28], //Número de contacto
        ]);

        // Constancia tramite de titulacón (ID 1)
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 15, 'idRequisito' => 1], // Nombre
            ['idTramite' => 15, 'idRequisito' => 2], // Matricula
            ['idTramite' => 15, 'idRequisito' => 3], // Periodo
            ['idTramite' => 15, 'idRequisito' => 4], // Semestre
            ['idTramite' => 15, 'idRequisito' => 5], // PE
        ]);

    }
}
