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
        Schema::table('users', function (Blueprint $table) {
            // Agregamos la columna idPE, que puede ser nula
            $table->unsignedBigInteger('idPE')->nullable()->after('email');

            // (Opcional pero recomendado) Llave foránea para integridad referencial
            // Asegúrate de que tu tabla de programas se llame 'programas_educativos' y la llave sea 'idPE'
            $table->foreign('idPE')
                  ->references('idPE')
                  ->on('programas_educativos')
                  ->onDelete('set null'); // Si se borra el programa, el usuario queda con idPE null
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Primero borramos la llave foránea
            $table->dropForeign(['idPE']);
            // Luego borramos la columna
            $table->dropColumn('idPE');
        });
    }
};