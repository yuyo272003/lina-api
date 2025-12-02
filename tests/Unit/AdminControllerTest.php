<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Test;

class AdminControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Modificar tabla users (columnas dinámicas)
        if (!Schema::hasColumn('users', 'solicita_rol')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('solicita_rol')->default(false);
            });
        }
        if (!Schema::hasColumn('users', 'idPE')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unsignedBigInteger('idPE')->nullable();
            });
        }

        // 2. Tabla Campus (Necesaria para Facultad)
        if (!Schema::hasTable('campuses')) {
            Schema::create('campuses', function (Blueprint $table) {
                $table->id('idCampus');
                $table->string('nombreCampus');
                $table->timestamps();
            });
        }

        // 3. Tabla Facultades (Necesita idCampus)
        if (!Schema::hasTable('facultades')) {
            Schema::create('facultades', function (Blueprint $table) {
                $table->id('idFacultad'); 
                $table->string('nombreFacultad');
                $table->unsignedBigInteger('idCampus'); // <--- Requerido
                $table->timestamps();
            });
        }

        // 4. Tabla Programas Educativos (Necesita facultad_id)
        if (!Schema::hasTable('programas_educativos')) {
            Schema::create('programas_educativos', function (Blueprint $table) {
                $table->id('idPE');
                $table->string('nombrePE');
                $table->unsignedBigInteger('facultad_id');
                $table->timestamps();
            });
        }

        // 5. Roles
        DB::table('roles')->insertOrIgnore([
            ['IdRole' => 2, 'NombreRole' => 'Academico'],
            ['IdRole' => 5, 'NombreRole' => 'Coordinador General'],
            ['IdRole' => 6, 'NombreRole' => 'Coordinador PE'],
            ['IdRole' => 7, 'NombreRole' => 'Secretaria'],
            ['IdRole' => 8, 'NombreRole' => 'Contador'],
        ]);

        // 6. INSERTAR DATOS EN CADENA (Campus -> Facultad -> PE)
        
        // A) Crear Campus
        $idCampus = DB::table('campuses')->insertGetId([
            'nombreCampus' => 'Campus Xalapa'
        ]);

        // B) Crear Facultad (Con el Campus creado)
        $idFacultad = DB::table('facultades')->insertGetId([
            'nombreFacultad' => 'Facultad de Estadística e Informática',
            'idCampus' => $idCampus // <--- SOLUCIÓN: Agregamos el ID del campus
        ]);

        // C) Crear PE (Con la Facultad creada)
        DB::table('programas_educativos')->insert([
            'idPE' => 1, 
            'nombrePE' => 'Ingeniería de Software',
            'facultad_id' => $idFacultad
        ]);
    }

    // --- Helpers ---

    protected function crearAdmin()
    {
        $user = User::factory()->create(['name' => 'Admin User']);
        DB::table('role_usuario')->insert(['user_id' => $user->id, 'role_id' => 5]); 
        return $user;
    }

    protected function crearUsuarioSolicitante()
    {
        $user = User::factory()->create(['name' => 'Solicitante', 'solicita_rol' => true]);
        DB::table('role_usuario')->insert(['user_id' => $user->id, 'role_id' => 2]);
        return $user;
    }

    // --- Tests ---

    #[Test]
    public function admin_puede_ver_solicitudes_de_rol()
    {
        $admin = $this->crearAdmin();
        $this->crearUsuarioSolicitante(); 

        $this->actingAs($admin);

        $response = $this->getJson('/api/admin/solicitudes-rol');

        $response->assertStatus(200)
                 ->assertJsonFragment(['name' => 'Solicitante']);
    }

    #[Test]
    public function admin_puede_asignar_rol_administrativo()
    {
        $admin = $this->crearAdmin();
        $targetUser = $this->crearUsuarioSolicitante();

        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/assign-local-role', [
            'user_id' => $targetUser->id,
            'role_id' => 8,
            'idPE' => null
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseMissing('role_usuario', ['user_id' => $targetUser->id, 'role_id' => 2]);
        $this->assertDatabaseHas('role_usuario', ['user_id' => $targetUser->id, 'role_id' => 8]);
        
        $targetUser->refresh();
        $this->assertFalse((bool)$targetUser->solicita_rol);
    }

    #[Test]
    public function admin_puede_asignar_rol_coordinador_pe_con_idpe()
    {
        $admin = $this->crearAdmin();
        $targetUser = $this->crearUsuarioSolicitante();

        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/assign-local-role', [
            'user_id' => $targetUser->id,
            'role_id' => 6,
            'idPE' => 1
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('role_usuario', ['user_id' => $targetUser->id, 'role_id' => 6]);
        
        $targetUser->refresh();
        $this->assertEquals(1, $targetUser->idPE);
    }

    #[Test]
    public function admin_puede_quitar_rol_administrativo()
    {
        $admin = $this->crearAdmin();
        
        $targetUser = User::factory()->create();
        DB::table('role_usuario')->insert(['user_id' => $targetUser->id, 'role_id' => 8]);

        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/remove-admin-role', [
            'user_id' => $targetUser->id
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('role_usuario', ['user_id' => $targetUser->id, 'role_id' => 2]);
        $this->assertDatabaseMissing('role_usuario', ['user_id' => $targetUser->id, 'role_id' => 8]);
    }

    #[Test]
    public function admin_no_puede_quitarse_rol_a_si_mismo()
    {
        $admin = $this->crearAdmin();
        $this->actingAs($admin);

        $response = $this->postJson('/api/admin/remove-admin-role', [
            'user_id' => $admin->id 
        ]);

        $response->assertStatus(403);
    }
    
    #[Test]
    public function obtiene_lista_de_programas_educativos()
    {
        $admin = $this->crearAdmin();
        $this->actingAs($admin);
        
        $response = $this->getJson('/api/programas-educativos');
        
        $response->assertStatus(200)
                 ->assertJsonFragment(['nombrePE' => 'Ingeniería de Software']);
    }
}