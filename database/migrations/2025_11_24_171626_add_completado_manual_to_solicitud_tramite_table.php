<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('solicitud_tramite', function (Blueprint $table) {
            // Agregamos la columna booleana, por defecto en falso (0)
            // 'after' es opcional, sirve para ordenar la columna visualmente en la BD
            $table->boolean('completado_manual')
                  ->default(0)
                  ->after('ruta_archivo_final'); 
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('solicitud_tramite', function (Blueprint $table) {
            $table->dropColumn('completado_manual');
        });
    }
};