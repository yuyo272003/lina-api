<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Solicitud;
use App\Mail\SolicitudCompletadaMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;
use PHPUnit\Framework\Attributes\Test;

class SecretarioControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles necesarios
        DB::table('roles')->insertOrIgnore([
            ['IdRole' => 3, 'NombreRole' => 'Estudiante'],
            ['IdRole' => 8, 'NombreRole' => 'Secretario'],
        ]);
        
        // 2. Datos base para evitar errores de FK en estudiantes
        $idCampus = DB::table('campuses')->insertGetId(['nombreCampus' => 'Xalapa']);
        $idFacultad = DB::table('facultades')->insertGetId(['nombreFacultad' => 'FCA', 'idCampus' => $idCampus]);
        $this->idPE = DB::table('programas_educativos')->insertGetId(['nombrePE' => 'Sistemas', 'facultad_id' => $idFacultad]);
    }

    // --- Helpers ---

    protected function crearSecretario()
    {
        $user = User::factory()->create(['email' => 'secre_' . uniqid() . '@uv.mx']);
        DB::table('role_usuario')->insert(['user_id' => $user->id, 'role_id' => 8]);
        return $user;
    }

    protected function crearEstudiante()
    {
        $user = User::factory()->create(['email' => 'alumno_' . uniqid() . '@uv.mx']);
        DB::table('role_usuario')->insert(['user_id' => $user->id, 'role_id' => 3]);
        
        DB::table('estudiantes')->insert([
            'user_id' => $user->id,
            'idPE' => $this->idPE,
            'matriculaEstudiante' => 'S' . rand(1000000, 9999999),
            'grupoEstudiante' => '101'
        ]);

        return $user;
    }

    protected function crearTramite($nombre = 'Tramite Test')
    {
        return DB::table('tramites')->insertGetId([
            'nombreTramite' => $nombre,
            'costoTramite' => 100.00, // Corrección: Campo requerido por la BD
            'created_at' => now(),
            'updated_at' => now()
        ]);
    }

    #[Test]
    public function secretario_puede_subir_archivo_para_tramite_y_se_guarda()
    {
        Storage::fake('public'); // Simulamos el disco

        $secretario = $this->crearSecretario();
        $estudiante = $this->crearEstudiante();
        
        // Crear Solicitud y asignar trámite
        $solicitud = Solicitud::create([
            'folio' => 'SOL-FILE',
            'user_id' => $estudiante->id,
            'estado' => 'en revisión 3'
        ]);
        
        $tramiteId = $this->crearTramite();
        $solicitud->tramites()->attach($tramiteId); // Relación pivote vacía

        $file = UploadedFile::fake()->create('oficio_firmado.pdf', 100); // 100kb

        $this->actingAs($secretario);

        // 1. Ejecutar Endpoint
        $response = $this->postJson("api/solicitudes/{$solicitud->idSolicitud}/subir-archivo", [
            'tramite_id' => $tramiteId,
            'archivo' => $file
        ]);

        // 2. Verificar Respuesta
        $response->assertStatus(200)
                 ->assertJson(['message' => "Archivo para trámite $tramiteId subido con éxito."]);

        // 3. Verificar Base de Datos (Pivot actualizado)
        $pivot = DB::table('solicitud_tramite')
            ->where('idSolicitud', $solicitud->idSolicitud)
            ->where('idTramite', $tramiteId)
            ->first();

        $this->assertNotNull($pivot->ruta_archivo_final, 'La ruta del archivo no se guardó en la BD.');

        // 4. Verificar Storage (El archivo existe en el disco fake)
        Storage::disk('public')->assertExists($pivot->ruta_archivo_final);
    }

    #[Test]
    public function secretario_puede_marcar_tramite_manual()
    {
        $secretario = $this->crearSecretario();
        $estudiante = $this->crearEstudiante();
        
        $solicitud = Solicitud::create([
            'folio' => 'SOL-MANUAL',
            'user_id' => $estudiante->id,
            'estado' => 'en revisión 3'
        ]);
        
        $tramiteId = $this->crearTramite();
        $solicitud->tramites()->attach($tramiteId);

        $this->actingAs($secretario);

        $response = $this->postJson("api/solicitudes/{$solicitud->idSolicitud}/marcar-manual", [
            'tramite_id' => $tramiteId
        ]);

        $response->assertStatus(200);

        // Verificar Pivot
        $this->assertDatabaseHas('solicitud_tramite', [
            'idSolicitud' => $solicitud->idSolicitud,
            'idTramite' => $tramiteId,
            'completado_manual' => 1,
            'ruta_archivo_final' => null // Debe limpiarse si había algo
        ]);
    }

    #[Test]
    public function completar_falla_si_hay_tramites_pendientes()
    {
        $secretario = $this->crearSecretario();
        $estudiante = $this->crearEstudiante();
        
        $solicitud = Solicitud::create([
            'folio' => 'SOL-PENDING',
            'user_id' => $estudiante->id,
            'estado' => 'en revisión 3'
        ]);
        
        // Asignamos 2 trámites
        $t1 = $this->crearTramite('T1');
        $t2 = $this->crearTramite('T2');
        $solicitud->tramites()->attach([$t1, $t2]);

        // Completamos solo el T1 manualmente
        $solicitud->tramites()->updateExistingPivot($t1, ['completado_manual' => 1]);
        
        // El T2 sigue pendiente...

        $this->actingAs($secretario);

        $response = $this->postJson("api/solicitudes/{$solicitud->idSolicitud}/completar");

        // Esperamos error 422 Unprocessable Entity
        $response->assertStatus(422)
                 ->assertJsonFragment([
                     'message' => "Aún faltan 1 trámites por gestionar (subir archivo o marcar completado)."
                 ]);
        
        // El estado NO debe haber cambiado
        $this->assertDatabaseHas('solicitudes', [
            'idSolicitud' => $solicitud->idSolicitud,
            'estado' => 'en revisión 3'
        ]);
    }

    #[Test]
    public function completar_exitoso_cambia_estado_y_envia_correo()
    {
        Mail::fake();

        $secretario = $this->crearSecretario();
        $estudiante = $this->crearEstudiante();
        
        $solicitud = Solicitud::create([
            'folio' => 'SOL-COMPLETE',
            'user_id' => $estudiante->id,
            'estado' => 'en revisión 3'
        ]);
        
        $t1 = $this->crearTramite();
        $solicitud->tramites()->attach($t1);

        // Simulamos que ya está completado (manual o archivo)
        $solicitud->tramites()->updateExistingPivot($t1, ['completado_manual' => 1]);

        $this->actingAs($secretario);

        $response = $this->postJson("api/solicitudes/{$solicitud->idSolicitud}/completar");

        $response->assertStatus(200)
                 ->assertJson(['message' => 'Proceso finalizado con éxito.']);

        // 1. Verifica cambio de estado
        $this->assertDatabaseHas('solicitudes', [
            'idSolicitud' => $solicitud->idSolicitud,
            'estado' => 'completado'
        ]);

        // 2. Verifica envío de correo
        Mail::assertSent(SolicitudCompletadaMail::class, function ($mail) use ($estudiante) {
            return $mail->hasTo($estudiante->email);
        });
    }

    #[Test]
    public function validar_que_tramite_pertenece_a_solicitud_al_subir()
    {
        Storage::fake('public');
        $secretario = $this->crearSecretario();
        $estudiante = $this->crearEstudiante();
        
        $solicitud = Solicitud::create(['folio' => 'SOL-A', 'user_id' => $estudiante->id, 'estado' => 'en revisión 3']);
        
        $tramiteAjeno = $this->crearTramite('Tramite Ajeno');
        // NO lo adjuntamos a esta solicitud (attach)

        $file = UploadedFile::fake()->create('doc.pdf');

        $this->actingAs($secretario);

        // Intentamos subir archivo para un trámite que no está en la solicitud
        $response = $this->postJson("api/solicitudes/{$solicitud->idSolicitud}/subir-archivo", [
            'tramite_id' => $tramiteAjeno,
            'archivo' => $file
        ]);

        $response->assertStatus(422)
                 ->assertJsonValidationErrors(['tramite_id']);
    }
}