<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega columnas faltantes (F-11) e índices de performance (F-17) a la tabla solicitudes.
     * - rol_rechazo: registra qué rol realizó el rechazo (usado en EstudianteController)
     * - ruta_comprobante: ruta del archivo comprobante de pago subido por el estudiante
     * - Índices en columnas de alta consulta para mejorar performance con escala
     */
    public function up(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            // F-11: Columnas faltantes que ya se usan en el código
            $table->unsignedTinyInteger('rol_rechazo')->nullable()->after('observaciones');
            $table->string('ruta_comprobante', 500)->nullable()->after('rol_rechazo');

            // F-17: Índices para columnas de alta consulta (WHERE / JOIN frecuentes)
            $table->index('user_id');
            $table->index('estado');
            $table->index(['user_id', 'estado'], 'solicitudes_user_estado_index');
        });

        // F-17: Índice en role_usuario para verificaciones RBAC frecuentes
        Schema::table('role_usuario', function (Blueprint $table) {
            $table->index('user_id');
        });

        // F-17: Índice en estudiantes para JOIN frecuente con users
        Schema::table('estudiantes', function (Blueprint $table) {
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::table('solicitudes', function (Blueprint $table) {
            $table->dropIndex(['user_id', 'estado']);
            $table->dropIndex(['estado']);
            $table->dropIndex(['user_id']);
            $table->dropColumn(['rol_rechazo', 'ruta_comprobante']);
        });

        Schema::table('role_usuario', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });

        Schema::table('estudiantes', function (Blueprint $table) {
            $table->dropIndex(['user_id']);
        });
    }
};
