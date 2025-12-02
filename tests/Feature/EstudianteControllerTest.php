<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use App\Models\Tramite;
use App\Models\Requisito;
use App\Models\Solicitud;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use PHPUnit\Framework\Attributes\Test;

class EstudianteControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // 1. Roles (usando los nombres reales de tu BD)
        DB::table('roles')->insertOrIgnore([
            ['IdRole' => 1, 'NombreRole' => 'Admin'],
            ['IdRole' => 2, 'NombreRole' => 'Academico'],
            ['IdRole' => 3, 'NombreRole' => 'Estudiante'],
            ['IdRole' => 4, 'NombreRole' => 'Egresado'],
        ]);

        // 2. Configuración
        DB::table('configuraciones')->updateOrInsert(
            ['clave' => 'NUMERO_CUENTA_DESTINO'],
            [
                'valor' => '1234567890',
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );
    }

    #[Test]
    public function get_profile_retorna_datos_del_estudiante_autenticado()
    {
        $user = User::factory()->create();
        
        $campus = \App\Models\Campus::factory()->create();
        $facultad = \App\Models\Facultad::factory()->create(['idCampus' => $campus->idCampus]);
        $pe = \App\Models\ProgramaEducativo::factory()->create(['facultad_id' => $facultad->idFacultad]);

        DB::table('estudiantes')->insert([
            'user_id' => $user->id,
            'matriculaEstudiante' => 'zS123456',
            'idPE' => $pe->idPE,
            'grupoEstudiante' => '501',
        ]);

        $this->actingAs($user);

        // CORRECCIÓN: Ruta real según tu api.php
        $response = $this->getJson('api/perfil-estudiante');

        $response->assertStatus(200)
                 ->assertJson([
                     'matricula' => 'zS123456',
                     'grupo' => '501',
                     'correo_institucional' => $user->email
                 ]);
    }

    #[Test]
    public function store_crea_solicitud_guarda_archivos_y_retorna_pdf()
    {
        Storage::fake('public');
        
        Pdf::shouldReceive('loadView')->andReturnSelf();
        Pdf::shouldReceive('output')->andReturn('CONTENIDO_DEL_PDF_FALSO');

        $user = User::factory()->create();
        $this->actingAs($user);

        // CORRECCIÓN: Quitamos 'descripcionTramite' porque tu tabla no la tiene.
        $idTramite = DB::table('tramites')->insertGetId([
            'nombreTramite' => 'Constancia de estudios',
            'costoTramite' => 12.00,
            'created_at' => now(),
            'updated_at' => now()
        ]);
        
        // Creamos el requisito manualmente para asegurar IDs y campos
        $idRequisito = DB::table('requisitos')->insertGetId([
            'nombreRequisito' => 'INE',
            'tipo' => 'documento',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $tramitesData = [
            [
                'id' => $idTramite,
                'respuestas' => [
                    'INE' => 'valor_temporal'
                ]
            ]
        ];

        $archivo = UploadedFile::fake()->create('ine.pdf', 100, 'application/pdf');

        // CORRECCIÓN: Ruta real según tu api.php (/solicitudes)
        $response = $this->postJson('api/solicitudes', [
            'tramites' => json_encode($tramitesData),
            'files' => [
                $idTramite => [
                    'INE' => $archivo
                ]
            ]
        ]);

        $response->assertStatus(200);
        $this->assertTrue($response->headers->contains('content-type', 'application/pdf'));

        $this->assertDatabaseHas('solicitudes', [
            'user_id' => $user->id,
            'estado' => 'en proceso'
        ]);
    }

    #[Test]
    public function subir_comprobante_actualiza_estado_correctamente()
    {
        Storage::fake('public');

        $user = User::factory()->create();
        // Usamos Factory pero aseguramos que el ID se llame idSolicitud si el modelo lo requiere,
        // o dejamos que Laravel lo maneje si el modelo tiene protected $primaryKey = 'idSolicitud';
        $solicitud = Solicitud::factory()->create(['user_id' => $user->id, 'estado' => 'en proceso']);
        
        $this->actingAs($user);

        $archivo = UploadedFile::fake()->create('pago.pdf', 100, 'application/pdf');

        // CORRECCIÓN: Ruta real (/solicitudes/{id}/comprobante)
        $response = $this->postJson("api/solicitudes/{$solicitud->idSolicitud}/comprobante", [
            'comprobante' => $archivo
        ]);

        $response->assertStatus(200);

        $this->assertDatabaseHas('solicitudes', [
            'idSolicitud' => $solicitud->idSolicitud,
            'estado' => 'en revisión 1'
        ]);
    }

    #[Test]
    public function cancelar_solicitud_cambia_estado_a_cancelada()
    {
        $user = User::factory()->create();
        $solicitud = Solicitud::factory()->create(['user_id' => $user->id, 'estado' => 'en proceso']);

        $this->actingAs($user);

        // CORRECCIÓN:
        // 1. Ruta real (/solicitudes/{id}/cancelar)
        // 2. Verbo HTTP correcto (PATCH en lugar de POST)
        $response = $this->patchJson("api/solicitudes/{$solicitud->idSolicitud}/cancelar");

        $response->assertStatus(200);

        $this->assertDatabaseHas('solicitudes', [
            'idSolicitud' => $solicitud->idSolicitud,
            'estado' => 'cancelada',
            'observaciones' => 'Cancelada por el usuario.'
        ]);
    }

    #[Test]
    public function no_puede_cancelar_solicitud_de_otro_usuario()
    {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create(); // El atacante
        $solicitud = Solicitud::factory()->create(['user_id' => $user1->id]);

        $this->actingAs($user2);

        // CORRECCIÓN: Verbo PATCH
        $response = $this->patchJson("api/solicitudes/{$solicitud->idSolicitud}/cancelar");

        $response->assertStatus(403);
    }
}