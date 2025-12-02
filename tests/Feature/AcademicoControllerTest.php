<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Academico;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Test;

class AcademicoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Modificar tabla Users para agregar 'solicita_rol' si no existe
        if (!Schema::hasColumn('users', 'solicita_rol')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('solicita_rol')->default(false);
            });
        }

        // 2. Tablas auxiliares
        
        // Tabla Campus
        if (!Schema::hasTable('campuses')) {
            Schema::create('campuses', function (Blueprint $table) {
                $table->id('idCampus');
                $table->string('nombreCampus');
                $table->timestamps();
            });
        }

        // Tabla Facultades
        if (!Schema::hasTable('facultades')) {
            Schema::create('facultades', function (Blueprint $table) {
                $table->id('idFacultad');
                $table->string('nombreFacultad');
                $table->unsignedBigInteger('idCampus');
                $table->timestamps();
            });
        }

        // Tabla Academicos
        // CORRECCIÓN: Si la tabla no existe, la creamos usando user_id
        if (!Schema::hasTable('academicos')) {
            Schema::create('academicos', function (Blueprint $table) {
                $table->id('idAcademico');
                $table->string('NoPersonalAcademico');
                $table->string('RfcAcademico')->nullable();
                $table->unsignedBigInteger('idFacultad');
                $table->unsignedBigInteger('user_id'); // <--- CAMBIO: Convención estándar Laravel
                $table->timestamps();
            });
        }

        // 3. Roles
        DB::table('roles')->insertOrIgnore([
            ['IdRole' => 2, 'NombreRole' => 'Academico'],
            ['IdRole' => 3, 'NombreRole' => 'Estudiante'],
        ]);
    }

    // --- Helpers ---

    protected function crearAcademicoCompleto()
    {
        // 1. Crear Campus
        $campusId = DB::table('campuses')->insertGetId([
            'nombreCampus' => 'Campus Xalapa'
        ]);

        // 2. Crear Facultad
        $facultadId = DB::table('facultades')->insertGetId([
            'nombreFacultad' => 'Facultad de Matemáticas',
            'idCampus' => $campusId
        ]);

        // 3. Crear Usuario
        $user = User::factory()->create(['name' => 'Dr. Prueba']);
        
        // Asignar rol Académico (2)
        DB::table('role_usuario')->insert(['user_id' => $user->id, 'role_id' => 2]);

        // 4. Crear Perfil Académico vinculado
        // CORRECCIÓN: Usamos 'user_id' en lugar de 'idUser'
        DB::table('academicos')->insert([
            'NoPersonalAcademico' => '12345',
            'RfcAcademico' => 'RFC123456',
            'idFacultad' => $facultadId,
            'user_id' => $user->id // <--- AQUÍ ESTABA EL ERROR
        ]);

        return $user;
    }

    // --- Tests ---

    #[Test]
    public function puede_obtener_perfil_academico_completo()
    {
        $user = $this->crearAcademicoCompleto();
        $this->actingAs($user);

        $response = $this->getJson('/api/perfil-academico');

        $response->assertStatus(200)
                 ->assertJson([
                     'nombre_completo' => 'Dr. Prueba',
                     'numero_personal' => '12345',
                     'facultad' => 'Facultad de Matemáticas',
                     'campus' => 'Campus Xalapa',
                     'rfc' => 'RFC123456',
                     'correo_institucional' => $user->email
                 ]);
    }

    #[Test]
    public function devuelve_404_si_usuario_no_tiene_perfil_academico()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->getJson('/api/perfil-academico');

        $response->assertStatus(404)
                 ->assertJson(['error' => 'Perfil de académico no encontrado para este usuario.']);
    }

    #[Test]
    public function academico_puede_solicitar_rol()
    {
        $user = User::factory()->create();
        DB::table('role_usuario')->insert(['user_id' => $user->id, 'role_id' => 2]);

        $this->actingAs($user);

        $response = $this->postJson('/api/academico/solicitar-rol');

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Solicitud enviada correctamente.']);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'solicita_rol' => true
        ]);
    }

    #[Test]
    public function usuario_sin_rol_academico_no_puede_solicitar_rol()
    {
        $user = User::factory()->create();
        DB::table('role_usuario')->insert(['user_id' => $user->id, 'role_id' => 3]);

        $this->actingAs($user);

        $response = $this->postJson('/api/academico/solicitar-rol');

        $response->assertStatus(403)
                 ->assertJson(['message' => 'Acción no permitida.']);
    }

    #[Test]
    public function puede_obtener_estado_de_solicitud_rol()
    {
        $user = User::factory()->create(['solicita_rol' => true]);
        $this->actingAs($user);

        $response = $this->getJson('/api/academico/estado-rol');

        $response->assertStatus(200)
                 ->assertJson(['solicita_rol' => true]);
    }
}