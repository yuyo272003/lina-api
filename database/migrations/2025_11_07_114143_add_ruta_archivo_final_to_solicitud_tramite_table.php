<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// El nombre de la clase será el que generó artisan
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Usamos Schema::table() para MODIFICAR la tabla
        Schema::table('solicitud_tramite', function (Blueprint $table) {

            // Añadimos nuestra nueva columna
            $table->string('ruta_archivo_final', 255) // VARCHAR(255)
            ->nullable()                      // Permite valores NULL
            ->after('datos_requisitos');       // La coloca después de la columna 'datos_requisitos'
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Esto permite revertir la migración (ej. con 'migrate:rollback')
        Schema::table('solicitud_tramite', function (Blueprint $table) {
            $table->dropColumn('ruta_archivo_final');
        });
    }
};
