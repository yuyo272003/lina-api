<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * F-12: Cambia el campo 'estado' de VARCHAR a ENUM para prevenir estados inválidos.
     * Se realiza en dos pasos para compatibilidad con datos existentes:
     * 1. Actualizar datos que no coincidan con los valores permitidos
     * 2. Modificar la columna a ENUM
     */
    public function up(): void
    {
        // Normalizar datos existentes antes del cambio de tipo
        DB::statement("
            UPDATE solicitudes
            SET estado = 'en proceso'
            WHERE estado NOT IN (
                'iniciado', 'en proceso', 'en revisión 1', 'en revisión 2',
                'en revisión 3', 'completado', 'rechazada', 'cancelada'
            )
        ");

        // SQLite no soporta ENUM ni MODIFY COLUMN; solo aplicar en MySQL/MariaDB
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE solicitudes
                MODIFY COLUMN estado ENUM(
                    'iniciado',
                    'en proceso',
                    'en revisión 1',
                    'en revisión 2',
                    'en revisión 3',
                    'completado',
                    'rechazada',
                    'cancelada'
                ) NOT NULL DEFAULT 'en proceso'
            ");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            DB::statement("
                ALTER TABLE solicitudes
                MODIFY COLUMN estado VARCHAR(255) NOT NULL DEFAULT 'en proceso'
            ");
        }
    }
};
