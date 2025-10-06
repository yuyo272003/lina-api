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
    Schema::create('academicos', function (Blueprint $table) {
        $table->id('idAcademico');
        $table->string('NoPersonalAcademico')->nullable();
        $table->string('RfcAcademico')->nullable();

        // Relación con la tabla Usuario
        $table->unsignedBigInteger('user_id');
        $table->foreign('user_id')->references('id')->on('users');

        $table->integer('CreatedBy');
        $table->integer('UpdatedBy');
        $table->timestamps();
    });
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('academico');
    }
};
