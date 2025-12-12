<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Solicitud;
use App\Mail\SolicitudRechazadaCoordinadorMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\Test;

class CoordinadorControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles necesarios
        // Aseguramos que existan el rol de estudiante y coordinador
        DB::table('roles')->insertOrIgnore([
            ['IdRole' => 3, 'NombreRole' => 'Estudiante'],
            ['IdRole' => 5, 'NombreRole' => 'Coordinador'],
        ]);
    }

    // --- Helpers ---

    protected function crearCoordinador()
    {
        // Email único para evitar colisiones
        $user = User::factory()->create(['email' => 'coord_' . uniqid() . '@uv.mx']);
        DB::table('role_usuario')->insert(['user_id' => $user->id, 'role_id' => 5]);
        return $user;
    }

    protected function crearEstudiante()
    {
        // Email único
        $user = User::factory()->create(['email' => 'alumno_' . uniqid() . '@uv.mx']);
        DB::table('role_usuario')->insert(['user_id' => $user->id, 'role_id' => 3]);
        
        // Datos dummy para evitar errores de Foreign Key en estudiantes
        $idCampus = DB::table('campuses')->insertGetId(['nombreCampus' => 'Xalapa']);
        $idFacultad = DB::table('facultades')->insertGetId(['nombreFacultad' => 'FCA', 'idCampus' => $idCampus]);
        $idPE = DB::table('programas_educativos')->insertGetId(['nombrePE' => 'Sistemas', 'facultad_id' => $idFacultad]);

        DB::table('estudiantes')->insert([
            'user_id' => $user->id,
            'idPE' => $idPE,
            'matriculaEstudiante' => 'S' . rand(1000000, 9999999),
            'grupoEstudiante' => '101'
        ]);

        return $user;
    }

    #[Test]
    public function coordinador_puede_aprobar_solicitud_a_revision_2()
    {
        // 1. Preparar: Coordinador y Solicitud en "en revisión 1"
        $coordinador = $this->crearCoordinador();
        $estudiante = $this->crearEstudiante();

        $solicitud = Solicitud::create([
            'folio' => 'SOL-COORD-OK',
            'user_id' => $estudiante->id,
            'estado' => 'en revisión 1', // Estado correcto para que el coordinador actúe
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // 2. Actuar
        $this->actingAs($coordinador);
        
        $response = $this->patchJson("api/solicitudes/{$solicitud->idSolicitud}/estado", [
            'estado' => 'en revisión 2' // Avanzar
        ]);

        // 3. Verificar
        $response->assertStatus(200)
                 ->assertJson(['message' => 'Estado de la solicitud actualizado con éxito.']);

        $this->assertDatabaseHas('solicitudes', [
            'idSolicitud' => $solicitud->idSolicitud,
            'estado' => 'en revisión 2',
            'observaciones' => null, // Se debe limpiar si había algo
            'rol_rechazo' => null
        ]);
    }

    #[Test]
    public function coordinador_puede_rechazar_solicitud_y_se_envia_correo()
    {
        Mail::fake(); // Fake para el correo

        $coordinador = $this->crearCoordinador();
        $estudiante = $this->crearEstudiante();

        $solicitud = Solicitud::create([
            'folio' => 'SOL-COORD-REJ',
            'user_id' => $estudiante->id,
            'estado' => 'en revisión 1',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->actingAs($coordinador);

        $response = $this->patchJson("api/solicitudes/{$solicitud->idSolicitud}/estado", [
            'estado' => 'rechazada',
            'observaciones' => 'Documento ilegible.'
        ]);

        $response->assertStatus(200);

        // Verificamos DB
        $this->assertDatabaseHas('solicitudes', [
            'idSolicitud' => $solicitud->idSolicitud,
            'estado' => 'rechazada',
            'observaciones' => 'Documento ilegible.',
            'rol_rechazo' => 5 // ID del rol Coordinador
        ]);

        // Verificamos Correo Específico de Coordinador
        Mail::assertSent(SolicitudRechazadaCoordinadorMail::class, function ($mail) use ($estudiante) {
            return $mail->hasTo($estudiante->email);
        });
    }

    #[Test]
    public function no_autorizado_si_usuario_no_es_coordinador()
    {
        $estudiante = $this->crearEstudiante();
        
        $solicitud = Solicitud::create([
            'folio' => 'SOL-AUTH-ERR',
            'user_id' => $estudiante->id,
            'estado' => 'en revisión 1'
        ]);

        $this->actingAs($estudiante); // El estudiante intenta aprobarse a sí mismo

        $response = $this->patchJson("api/solicitudes/{$solicitud->idSolicitud}/estado", [
            'estado' => 'en revisión 2'
        ]);

        $response->assertStatus(403); // Forbidden
    }

    #[Test]
    public function falla_validacion_si_rechaza_sin_observaciones()
    {
        $coordinador = $this->crearCoordinador();
        $estudiante = $this->crearEstudiante();

        $solicitud = Solicitud::create([
            'folio' => 'SOL-VAL-ERR',
            'user_id' => $estudiante->id,
            'estado' => 'en revisión 1'
        ]);

        $this->actingAs($coordinador);

        $response = $this->patchJson("api/solicitudes/{$solicitud->idSolicitud}/estado", [
            'estado' => 'rechazada'
            // Omitimos 'observaciones'
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['observaciones']);
    }

    #[Test]
    public function falla_logica_si_intenta_aprobar_desde_estado_incorrecto()
    {
        $coordinador = $this->crearCoordinador();
        $estudiante = $this->crearEstudiante();

        // Solicitud "en proceso" (aún no enviada por el alumno)
        $solicitud = Solicitud::create([
            'folio' => 'SOL-PREMATURA',
            'user_id' => $estudiante->id,
            'estado' => 'en proceso' 
        ]);

        $this->actingAs($coordinador);

        // Intentamos aprobar a revisión 2
        $response = $this->patchJson("api/solicitudes/{$solicitud->idSolicitud}/estado", [
            'estado' => 'en revisión 2'
        ]);

        // Debe fallar porque el código valida: if ($estadoActual !== 'en revisión 1' && $nuevoEstado !== 'rechazada')
        $response->assertStatus(409)
         ->assertJsonFragment(['message' => "El estado actual es 'en proceso'. No se puede realizar la acción en este punto."]);
    }

    #[Test]
    public function coordinador_no_puede_usar_estados_invalidos()
    {
        $coordinador = $this->crearCoordinador();
        $estudiante = $this->crearEstudiante();

        $solicitud = Solicitud::create([
            'folio' => 'SOL-INV-ESTADO',
            'user_id' => $estudiante->id,
            'estado' => 'en revisión 1'
        ]);

        $this->actingAs($coordinador);

        // Intentar pasar directo a 'completado' o un estado inexistente
        $response = $this->patchJson("api/solicitudes/{$solicitud->idSolicitud}/estado", [
            'estado' => 'completado'
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['estado']);
    }
}