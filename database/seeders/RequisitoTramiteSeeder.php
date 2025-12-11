<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RequisitoTramiteSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('requisito_tramite')->delete();

        // 1. Constancia de Estudios
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 1, 'idRequisito' => 1], // Nombre
            ['idTramite' => 1, 'idRequisito' => 2], // Matricula
            ['idTramite' => 1, 'idRequisito' => 3], // Periodo
            ['idTramite' => 1, 'idRequisito' => 4], // Semestre
            ['idTramite' => 1, 'idRequisito' => 5], // PE
        ]);

        // 2. Constancia con avance acrediticio
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 2, 'idRequisito' => 1], 
            ['idTramite' => 2, 'idRequisito' => 2], 
            ['idTramite' => 2, 'idRequisito' => 3], 
            ['idTramite' => 2, 'idRequisito' => 4], 
            ['idTramite' => 2, 'idRequisito' => 5], 
        ]);

        // 3. Boleta
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 3, 'idRequisito' => 1], 
            ['idTramite' => 3, 'idRequisito' => 2], 
            ['idTramite' => 3, 'idRequisito' => 3], 
            ['idTramite' => 3, 'idRequisito' => 4], 
            ['idTramite' => 3, 'idRequisito' => 5], 
        ]);

        // 4. Carta de Presentación de Servicio Social
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 4, 'idRequisito' => 1], // Nombre
            ['idTramite' => 4, 'idRequisito' => 2], // Matricula
            ['idTramite' => 4, 'idRequisito' => 3], // Periodo
            ['idTramite' => 4, 'idRequisito' => 4], // Semestre (implícito en datos estudiante)
            ['idTramite' => 4, 'idRequisito' => 5], // PE
            ['idTramite' => 4, 'idRequisito' => 11], // Creditos PE
            ['idTramite' => 4, 'idRequisito' => 12], // Creditos Estudiante
            ['idTramite' => 4, 'idRequisito' => 13], // Avance
            ['idTramite' => 4, 'idRequisito' => 14], // Horas SS
            ['idTramite' => 4, 'idRequisito' => 15], // Fecha inicio
            ['idTramite' => 4, 'idRequisito' => 16], // Nombre empresa
            ['idTramite' => 4, 'idRequisito' => 17], // Datos empresa
            ['idTramite' => 4, 'idRequisito' => 18], // Correo empresa
            ['idTramite' => 4, 'idRequisito' => 19], // Atención a
            ['idTramite' => 4, 'idRequisito' => 20], // Fecha solicitud
        ]);

        // 5. Carta de presentación para Practicas Profesionales
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 5, 'idRequisito' => 1], 
            ['idTramite' => 5, 'idRequisito' => 2], 
            ['idTramite' => 5, 'idRequisito' => 3], 
            ['idTramite' => 5, 'idRequisito' => 4], 
            ['idTramite' => 5, 'idRequisito' => 5], 
            ['idTramite' => 5, 'idRequisito' => 11], 
            ['idTramite' => 5, 'idRequisito' => 12], 
            ['idTramite' => 5, 'idRequisito' => 13], 
            ['idTramite' => 5, 'idRequisito' => 14], // Horas Practica
            ['idTramite' => 5, 'idRequisito' => 15], // Fecha inicio
            ['idTramite' => 5, 'idRequisito' => 16], 
            ['idTramite' => 5, 'idRequisito' => 17], 
            ['idTramite' => 5, 'idRequisito' => 18], 
            ['idTramite' => 5, 'idRequisito' => 19], 
            ['idTramite' => 5, 'idRequisito' => 20], 
        ]);

        // 6. Constancia para IMSS
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 6, 'idRequisito' => 1], 
            ['idTramite' => 6, 'idRequisito' => 2], 
            ['idTramite' => 6, 'idRequisito' => 3], 
            ['idTramite' => 6, 'idRequisito' => 4], 
            ['idTramite' => 6, 'idRequisito' => 5], 
        ]);

        // 7. Constancia para PEMEX
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 7, 'idRequisito' => 1], 
            ['idTramite' => 7, 'idRequisito' => 2], 
            ['idTramite' => 7, 'idRequisito' => 3], 
            ['idTramite' => 7, 'idRequisito' => 4], 
            ['idTramite' => 7, 'idRequisito' => 5], 
        ]);

        // 8. Cardex
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 8, 'idRequisito' => 1], 
            ['idTramite' => 8, 'idRequisito' => 2], 
            ['idTramite' => 8, 'idRequisito' => 3], 
            ['idTramite' => 8, 'idRequisito' => 4], 
            ['idTramite' => 8, 'idRequisito' => 5], 
        ]);

        // 9. Baja Temporal
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 9, 'idRequisito' => 1], 
            ['idTramite' => 9, 'idRequisito' => 2], 
            ['idTramite' => 9, 'idRequisito' => 3], // Periodo (cubre "Periodo de la baja")
            ['idTramite' => 9, 'idRequisito' => 4], 
            ['idTramite' => 9, 'idRequisito' => 5], 
        ]);

        // 10. Certificación de documentos
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 10, 'idRequisito' => 1], // Nombre
            ['idTramite' => 10, 'idRequisito' => 2], // Matricula
            ['idTramite' => 10, 'idRequisito' => 3], // Periodo
            ['idTramite' => 10, 'idRequisito' => 4], // Semestre
            ['idTramite' => 10, 'idRequisito' => 5], // PE
            ['idTramite' => 10, 'idRequisito' => 29], // Acta de Nacimiento (Documento)
            ['idTramite' => 10, 'idRequisito' => 30], // Certificado Bachillerato (Documento)
        ]);

        // 11. Baja definitiva
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 11, 'idRequisito' => 1], // Nombre
            ['idTramite' => 11, 'idRequisito' => 2], // Matricula
            ['idTramite' => 11, 'idRequisito' => 3], // Periodo
            ['idTramite' => 11, 'idRequisito' => 4], // Semestre
        ]);

        // 12. Examen Extraordinario
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 12, 'idRequisito' => 1], 
            ['idTramite' => 12, 'idRequisito' => 2], 
            ['idTramite' => 12, 'idRequisito' => 3], 
            ['idTramite' => 12, 'idRequisito' => 4], 
            ['idTramite' => 12, 'idRequisito' => 5], 
            ['idTramite' => 12, 'idRequisito' => 6], // Experiencia
            ['idTramite' => 12, 'idRequisito' => 7], // Grupo
            ['idTramite' => 12, 'idRequisito' => 8], // NRC
            ['idTramite' => 12, 'idRequisito' => 9], // Docente
            ['idTramite' => 12, 'idRequisito' => 21], // Aula
            ['idTramite' => 12, 'idRequisito' => 22], // Fecha
            ['idTramite' => 12, 'idRequisito' => 23], // Hora
        ]);

        // 13. Examen Titulo
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 13, 'idRequisito' => 1], 
            ['idTramite' => 13, 'idRequisito' => 2], 
            ['idTramite' => 13, 'idRequisito' => 3], 
            ['idTramite' => 13, 'idRequisito' => 4], 
            ['idTramite' => 13, 'idRequisito' => 5], 
            ['idTramite' => 13, 'idRequisito' => 6], 
            ['idTramite' => 13, 'idRequisito' => 7], 
            ['idTramite' => 13, 'idRequisito' => 8], 
            ['idTramite' => 13, 'idRequisito' => 9], 
            ['idTramite' => 13, 'idRequisito' => 21], 
            ['idTramite' => 13, 'idRequisito' => 22], 
            ['idTramite' => 13, 'idRequisito' => 23], 
        ]);

        // 14. Examen UO
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 14, 'idRequisito' => 1], // Nombre
            ['idTramite' => 14, 'idRequisito' => 2], // Matricula
            ['idTramite' => 14, 'idRequisito' => 3], // Periodo
            ['idTramite' => 14, 'idRequisito' => 5], // PE
            ['idTramite' => 14, 'idRequisito' => 6], // Experiencia
            ['idTramite' => 14, 'idRequisito' => 10], // Correo Inst
            ['idTramite' => 14, 'idRequisito' => 24], // Docente 1
            ['idTramite' => 14, 'idRequisito' => 25], // Docente 2
            ['idTramite' => 14, 'idRequisito' => 26], // Excluir
            ['idTramite' => 14, 'idRequisito' => 27], // Correo Alt
            ['idTramite' => 14, 'idRequisito' => 28], // Contacto
        ]);

        // 15. Constancia tramite de titulación
        DB::table('requisito_tramite')->insert([
            ['idTramite' => 15, 'idRequisito' => 1], 
            ['idTramite' => 15, 'idRequisito' => 2], 
            ['idTramite' => 15, 'idRequisito' => 3], 
            ['idTramite' => 15, 'idRequisito' => 4], 
            ['idTramite' => 15, 'idRequisito' => 5], 
        ]);
    }
}