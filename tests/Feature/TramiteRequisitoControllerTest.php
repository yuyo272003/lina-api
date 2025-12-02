<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tramite;
use App\Models\Requisito;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use PHPUnit\Framework\Attributes\Test;

class TramiteRequisitoControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. SOLUCIÓN ROBUSTA: Recrear la tabla pivote manualmente en SQLite
        // USAMOS EL NOMBRE REAL DE TU BASE DE DATOS: 'requisito_tramite'
        
        if (Schema::hasTable('requisito_tramite')) {
            Schema::drop('requisito_tramite');
        }

        Schema::create('requisito_tramite', function (Blueprint $table) {
            $table->unsignedBigInteger('idTramite');
            $table->unsignedBigInteger('idRequisito');
            
            // Si tu tabla real no usa timestamps, puedes quitar esto.
            // Si los usa, asegúrate que tu modelo tenga ->withTimestamps()
            $table->timestamps(); 
        });

        // 2. Seed de Roles
        DB::table('roles')->insertOrIgnore([
            ['IdRole' => 3, 'NombreRole' => 'Estudiante'],
            ['IdRole' => 5, 'NombreRole' => 'Coordinador'],
        ]);
    }

    // --- Helpers ---

    protected function crearCoordinador()
    {
        $user = User::factory()->create(['email' => 'admin_' . uniqid() . '@uv.mx']);
        DB::table('role_usuario')->insert(['user_id' => $user->id, 'role_id' => 5]); 
        return $user;
    }

    protected function crearEstudiante()
    {
        $user = User::factory()->create(['email' => 'alumno_' . uniqid() . '@uv.mx']);
        DB::table('role_usuario')->insert(['user_id' => $user->id, 'role_id' => 3]); 
        return $user;
    }

    protected function crearRequisito($nombre = 'Requisito Test', $tipo = 'documento')
    {
        return Requisito::create([
            'nombreRequisito' => $nombre . '_' . uniqid(),
            'tipo' => $tipo
        ]);
    }

    #[Test]
    public function no_autorizado_si_usuario_no_es_coordinador()
    {
        $estudiante = $this->crearEstudiante(); 
        $this->actingAs($estudiante);

        $response = $this->getJson('api/gestion/tramites');
        $response->assertStatus(403);

        $response = $this->postJson('api/gestion/requisitos', []);
        $response->assertStatus(403);
    }

    #[Test]
    public function coordinador_puede_ver_lista_tramites()
    {
        $coordinador = $this->crearCoordinador();
        
        $req = $this->crearRequisito();
        $tramite = Tramite::create(['nombreTramite' => 'Tramite A', 'costoTramite' => 50]);
        $tramite->requisitos()->attach($req->idRequisito);

        $this->actingAs($coordinador);

        $response = $this->getJson('api/gestion/tramites');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     '*' => ['idTramite', 'nombreTramite', 'costoTramite', 'requisitos']
                 ]);
    }

    #[Test]
    public function coordinador_puede_crear_requisito()
    {
        $coordinador = $this->crearCoordinador();
        $this->actingAs($coordinador);

        $nombreReq = 'Acta de Nacimiento ' . uniqid();

        $response = $this->postJson('api/gestion/requisitos', [
            'nombreRequisito' => $nombreReq,
            'tipo' => 'documento'
        ]);

        $response->assertStatus(201)
                 ->assertJson(['nombreRequisito' => $nombreReq]);

        $this->assertDatabaseHas('requisitos', [
            'nombreRequisito' => $nombreReq,
            'tipo' => 'documento'
        ]);
    }

    #[Test]
    public function falla_crear_requisito_duplicado_o_tipo_invalido()
    {
        $coordinador = $this->crearCoordinador();
        $this->actingAs($coordinador);

        $req = $this->crearRequisito('Req Existente');

        // Intento 1: Nombre duplicado
        $response = $this->postJson('api/gestion/requisitos', [
            'nombreRequisito' => $req->nombreRequisito,
            'tipo' => 'dato'
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['nombreRequisito']);

        // Intento 2: Tipo inválido
        $response = $this->postJson('api/gestion/requisitos', [
            'nombreRequisito' => 'Nuevo Req',
            'tipo' => 'audio' 
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['tipo']);
    }

    #[Test]
    public function coordinador_puede_crear_tramite_con_requisitos()
    {
        $coordinador = $this->crearCoordinador();
        $this->actingAs($coordinador);

        $r1 = $this->crearRequisito('R1');
        $r2 = $this->crearRequisito('R2');

        $nombreTramite = 'Titulacion ' . uniqid();

        $response = $this->postJson('api/gestion/tramites', [
            'nombreTramite' => $nombreTramite,
            'costoTramite' => 1500.50,
            'requisito_ids' => [$r1->idRequisito, $r2->idRequisito]
        ]);

        $response->assertStatus(201);
        
        $this->assertDatabaseHas('tramites', [
            'nombreTramite' => $nombreTramite,
            'costoTramite' => 1500.50
        ]);

        $tramiteId = $response->json('idTramite');
        
        // CORREGIDO: Verificamos en 'requisito_tramite'
        $this->assertDatabaseHas('requisito_tramite', ['idTramite' => $tramiteId, 'idRequisito' => $r1->idRequisito]);
        $this->assertDatabaseHas('requisito_tramite', ['idTramite' => $tramiteId, 'idRequisito' => $r2->idRequisito]);
    }

    #[Test]
    public function coordinador_puede_actualizar_tramite()
    {
        $coordinador = $this->crearCoordinador();
        $this->actingAs($coordinador);

        $r1 = $this->crearRequisito('R1');
        $r2 = $this->crearRequisito('R2'); 
        
        $tramite = Tramite::create(['nombreTramite' => 'Tramite Old', 'costoTramite' => 100]);
        $tramite->requisitos()->attach($r1->idRequisito);

        $response = $this->putJson("api/gestion/tramites/{$tramite->idTramite}", [
            'nombreTramite' => 'Tramite Updated',
            'costoTramite' => 200,
            'requisito_ids' => [$r2->idRequisito] 
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('tramites', [
            'idTramite' => $tramite->idTramite,
            'nombreTramite' => 'Tramite Updated',
            'costoTramite' => 200
        ]);

        $this->assertDatabaseHas('requisito_tramite', ['idTramite' => $tramite->idTramite, 'idRequisito' => $r2->idRequisito]);
        $this->assertDatabaseMissing('requisito_tramite', ['idTramite' => $tramite->idTramite, 'idRequisito' => $r1->idRequisito]);
    }

    #[Test]
    public function coordinador_puede_eliminar_tramite()
    {
        $coordinador = $this->crearCoordinador();
        $this->actingAs($coordinador);

        $req = $this->crearRequisito();
        $tramite = Tramite::create(['nombreTramite' => 'To Delete', 'costoTramite' => 0]);
        $tramite->requisitos()->attach($req->idRequisito);

        $response = $this->deleteJson("api/gestion/tramites/{$tramite->idTramite}");

        $response->assertStatus(200);

        $this->assertDatabaseMissing('tramites', ['idTramite' => $tramite->idTramite]);
        
        $this->assertDatabaseMissing('requisito_tramite', ['idTramite' => $tramite->idTramite]);
        
        $this->assertDatabaseHas('requisitos', ['idRequisito' => $req->idRequisito]);
    }
}