<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Solicitud;
use App\Mail\SolicitudRechazadaMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;

class ContadorControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Seed de Roles necesarios para que funcione la autorización
        DB::table('roles')->insertOrIgnore([
            ['IdRole' => 3, 'NombreRole' => 'Estudiante'],
            ['IdRole' => 5, 'NombreRole' => 'Coordinador'],
            ['IdRole' => 7, 'NombreRole' => 'Contador'],
        ]);
    }

    // --- Helpers para crear usuarios ---

    protected function crearContador()
    {
        // Email aleatorio para evitar error de duplicados
        $user = User::factory()->create(['email' => 'conta_' . uniqid() . '@uv.mx']);
        DB::table('role_usuario')->insert(['user_id' => $user->id, 'role_id' => 7]); // Rol 7 = Contador
        return $user;
    }

    protected function crearEstudiante()
    {
        // Email aleatorio para evitar error de duplicados
        $user = User::factory()->create(['email' => 'alumno_' . uniqid() . '@uv.mx']);
        DB::table('role_usuario')->insert(['user_id' => $user->id, 'role_id' => 3]); // Rol 3 = Estudiante
        
        // Datos mínimos de estudiante (Campuses, Facultades, PE) para evitar errores de FK
        $idCampus = DB::table('campuses')->insertGetId(['nombreCampus' => 'Xalapa']);
        $idFacultad = DB::table('facultades')->insertGetId(['nombreFacultad' => 'FCA', 'idCampus' => $idCampus]);
        $idPE = DB::table('programas_educativos')->insertGetId(['nombrePE' => 'Sistemas', 'facultad_id' => $idFacultad]);
        
        // Matricula aleatoria
        DB::table('estudiantes')->insert([
            'user_id' => $user->id,
            'idPE' => $idPE,
            'matriculaEstudiante' => 'S' . rand(1000000, 9999999),
            'grupoEstudiante' => '101'
        ]);

        return $user;
    }

    #[Test]
    public function contador_puede_avanzar_solicitud_a_revision_3()
    {
        // 1. Preparar
        $contador = $this->crearContador();
        $estudiante = $this->crearEstudiante();

        $solicitud = Solicitud::create([
            'folio' => 'SOL-001',
            'user_id' => $estudiante->id,
            'estado' => 'en revisión 2',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // 2. Actuar
        $this->actingAs($contador);
        
        // CORRECCIÓN: Usamos patchJson porque tu ruta es Route::patch
        $response = $this->patchJson("api/solicitudes/{$solicitud->idSolicitud}/estado-contador", [
            'estado' => 'en revisión 3'
        ]);

        // 3. Verificar
        $response->assertStatus(200)
                 ->assertJson(['message' => 'Estado de la solicitud actualizado con éxito.']);

        $this->assertDatabaseHas('solicitudes', [
            'idSolicitud' => $solicitud->idSolicitud,
            'estado' => 'en revisión 3',
            'observaciones' => null, 
            'rol_rechazo' => null
        ]);
    }

    #[Test]
    public function contador_puede_rechazar_solicitud_y_se_envia_correo()
    {
        Mail::fake(); 

        $contador = $this->crearContador();
        $estudiante = $this->crearEstudiante();

        $solicitud = Solicitud::create([
            'folio' => 'SOL-REJECT',
            'user_id' => $estudiante->id,
            'estado' => 'en revisión 2',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->actingAs($contador);

        // CORRECCIÓN: patchJson
        $response = $this->patchJson("api/solicitudes/{$solicitud->idSolicitud}/estado-contador", [
            'estado' => 'rechazada',
            'observaciones' => 'El monto no coincide con la factura.'
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('solicitudes', [
            'idSolicitud' => $solicitud->idSolicitud,
            'estado' => 'rechazada',
            'observaciones' => 'El monto no coincide con la factura.',
            'rol_rechazo' => 7 
        ]);

        Mail::assertSent(SolicitudRechazadaMail::class, function ($mail) use ($estudiante) {
            return $mail->hasTo($estudiante->email);
        });
    }

    #[Test]
    public function no_autorizado_si_usuario_no_es_administrativo()
    {
        $estudiante = $this->crearEstudiante(); 
        $otroEstudiante = $this->crearEstudiante();

        $solicitud = Solicitud::create([
            'folio' => 'SOL-AUTH',
            'user_id' => $otroEstudiante->id,
            'estado' => 'en revisión 2'
        ]);

        $this->actingAs($estudiante); 

        // CORRECCIÓN: patchJson
        $response = $this->patchJson("api/solicitudes/{$solicitud->idSolicitud}/estado-contador", [
            'estado' => 'en revisión 3'
        ]);

        $response->assertStatus(403); 
    }

    #[Test]
    public function falla_validacion_si_rechaza_sin_observaciones()
    {
        $contador = $this->crearContador();
        $estudiante = $this->crearEstudiante();

        $solicitud = Solicitud::create([
            'folio' => 'SOL-VAL',
            'user_id' => $estudiante->id,
            'estado' => 'en revisión 2'
        ]);

        $this->actingAs($contador);

        // CORRECCIÓN: patchJson
        $response = $this->patchJson("api/solicitudes/{$solicitud->idSolicitud}/estado-contador", [
            'estado' => 'rechazada',
            // Sin observaciones
        ]);

        $response->assertStatus(422) 
                 ->assertJsonValidationErrors(['observaciones']);
    }

    #[Test]
    public function falla_logica_si_estado_previo_es_incorrecto()
    {
        $contador = $this->crearContador();
        $estudiante = $this->crearEstudiante();

        $solicitud = Solicitud::create([
            'folio' => 'SOL-EARLY',
            'user_id' => $estudiante->id,
            'estado' => 'en proceso' 
        ]);

        $this->actingAs($contador);

        // CORRECCIÓN: patchJson
        $response = $this->patchJson("api/solicitudes/{$solicitud->idSolicitud}/estado-contador", [
            'estado' => 'en revisión 3'
        ]);

        $response->assertStatus(409) 
                 ->assertJsonFragment(['message' => "El estado actual es 'en proceso'. No se puede realizar esta acción desde esta etapa."]);
    }

    #[Test]
    public function contador_no_puede_usar_estados_invalidos()
    {
        $contador = $this->crearContador();
        $estudiante = $this->crearEstudiante();

        $solicitud = Solicitud::create([
            'folio' => 'SOL-INV',
            'user_id' => $estudiante->id,
            'estado' => 'en revisión 2'
        ]);

        $this->actingAs($contador);

        // CORRECCIÓN: patchJson
        $response = $this->patchJson("api/solicitudes/{$solicitud->idSolicitud}/estado-contador", [
            'estado' => 'completado'
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['estado']);
    }
}