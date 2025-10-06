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
    Schema::create('role_usuario', function (Blueprint $table) {
        $table->unsignedBigInteger('user_id');
        $table->unsignedBigInteger('role_id');

        $table->foreign('user_id')->references('id')->on('users');
        $table->foreign('role_id')->references('IdRole')->on('roles');

        $table->primary(['user_id', 'role_id']);

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
        Schema::dropIfExists('role_usuario');
    }
};
