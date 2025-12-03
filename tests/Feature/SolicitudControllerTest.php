<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Barryvdh\DomPDF\Facade\Pdf;
use PHPUnit\Framework\Attributes\Test;

class SolicitudControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Insertamos los roles
        DB::table('roles')->insertOrIgnore([
            ['IdRole' => 1, 'NombreRole' => 'Admin'],
            ['IdRole' => 2, 'NombreRole' => 'Academico'],
            ['IdRole' => 3, 'NombreRole' => 'Estudiante'],
            ['IdRole' => 4, 'NombreRole' => 'Egresado'],
            ['IdRole' => 5, 'NombreRole' => 'Coordinador General'],
            ['IdRole' => 6, 'NombreRole' => 'Coordinador PE'],
            ['IdRole' => 7, 'NombreRole' => 'Contador'],
            ['IdRole' => 8, 'NombreRole' => 'Secretario'],
        ]);
    }

    /**
     * Helper corregido para generar emails únicos automáticamente
     */
    protected function crearUsuarioConRol($roleId, $email = null)
    {
        // Si no se pasa email, generamos uno aleatorio para evitar UniqueConstraintViolation
        $emailFinal = $email ?? 'user_' . uniqid() . '@uv.mx';

        $userId = DB::table('users')->insertGetId([
            'name' => 'Usuario Test ' . $roleId,
            'first_name' => 'Usuario',
            'last_name' => 'Test',
            'email' => $emailFinal,
            'password' => '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', // password hash
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('role_usuario')->insert([
            'user_id' => $userId,
            'role_id' => $roleId
        ]);

        return User::find($userId);
    }

    /**
     * Helper corregido para generar matriculas únicas
     */
    protected function vincularEstudiante($userId)
    {
        $idCampus = DB::table('campuses')->insertGetId(['nombreCampus' => 'Xalapa']);
        $idFacultad = DB::table('facultades')->insertGetId(['nombreFacultad' => 'FCA', 'idCampus' => $idCampus]);
        $idPE = DB::table('programas_educativos')->insertGetId(['nombrePE' => 'Sistemas', 'facultad_id' => $idFacultad]);

        // Generamos matricula aleatoria para evitar error de Unique Constraint
        $matricula = 'S' . rand(1000000, 9999999);

        DB::table('estudiantes')->insert([
            'user_id' => $userId,
            'idPE' => $idPE,
            'matriculaEstudiante' => $matricula, 
            'grupoEstudiante' => '101'
        ]);
    }

    #[Test]
    public function estudiante_solo_ve_sus_propias_solicitudes_en_index()
    {
        $estudiante1 = $this->crearUsuarioConRol(3); // Email aleatorio
        $this->vincularEstudiante($estudiante1->id); // Matrícula aleatoria
        
        $estudiante2 = $this->crearUsuarioConRol(3); // Email aleatorio
        $this->vincularEstudiante($estudiante2->id); // Matrícula aleatoria

        DB::table('solicitudes')->insert([
            ['idSolicitud' => 1, 'folio' => 'SOL-1', 'user_id' => $estudiante1->id, 'estado' => 'en proceso', 'created_at' => now(), 'updated_at' => now()],
            ['idSolicitud' => 2, 'folio' => 'SOL-2', 'user_id' => $estudiante2->id, 'estado' => 'en proceso', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($estudiante1);

        $response = $this->getJson('api/solicitudes');

        $response->assertStatus(200);
        $data = $response->json();
        
        // Verificamos que solo traiga 1 registro y sea el folio correcto
        $this->assertCount(1, $data);
        $this->assertEquals('SOL-1', $data[0]['folio']);
    }

    #[Test]
    public function coordinador_ve_solicitudes_en_estados_correctos()
    {
        // NOTA IMPORTANTE: Si este test falla con "Syntax error near SEPARATOR",
        // lee la explicación abajo del código sobre SQLite vs MySQL.
        
        $coordinador = $this->crearUsuarioConRol(5);
        $estudiante = $this->crearUsuarioConRol(3);
        $this->vincularEstudiante($estudiante->id);

        DB::table('solicitudes')->insert([
            ['idSolicitud' => 1, 'folio' => 'VISIBLE-REV1', 'user_id' => $estudiante->id, 'estado' => 'en revisión 1', 'created_at' => now(), 'updated_at' => now()],
            ['idSolicitud' => 2, 'folio' => 'VISIBLE-REV2', 'user_id' => $estudiante->id, 'estado' => 'en revisión 2', 'created_at' => now(), 'updated_at' => now()],
            ['idSolicitud' => 3, 'folio' => 'NO-VISIBLE-PROC', 'user_id' => $estudiante->id, 'estado' => 'en proceso', 'created_at' => now(), 'updated_at' => now()],
            ['idSolicitud' => 4, 'folio' => 'VISIBLE-COMP', 'user_id' => $estudiante->id, 'estado' => 'completado', 'created_at' => now(), 'updated_at' => now()],
        ]);

        $this->actingAs($coordinador);

        $response = $this->getJson('api/solicitudes');

        $response->assertStatus(200);
        $folios = collect($response->json())->pluck('folio')->toArray();

        $this->assertContains('VISIBLE-REV1', $folios);
        $this->assertContains('VISIBLE-REV2', $folios);
        $this->assertContains('VISIBLE-COMP', $folios);
        $this->assertNotContains('NO-VISIBLE-PROC', $folios);
    }

    #[Test]
    public function show_permite_acceso_al_dueno_y_deniega_a_otros()
    {
        $dueno = $this->crearUsuarioConRol(3);
        $this->vincularEstudiante($dueno->id);

        $intruso = $this->crearUsuarioConRol(3);

        $idSolicitud = DB::table('solicitudes')->insertGetId([
            'folio' => 'SOL-SHOW',
            'user_id' => $dueno->id,
            'estado' => 'en proceso',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Caso A: El dueño accede
        $this->actingAs($dueno);
        $response = $this->getJson("api/solicitudes/{$idSolicitud}");
        $response->assertStatus(200)
                 ->assertJson(['folio' => 'SOL-SHOW']);

        // Caso B: El intruso intenta acceder
        $this->actingAs($intruso);
        $response = $this->getJson("api/solicitudes/{$idSolicitud}");
        $response->assertStatus(403); 
    }

    #[Test]
    public function admin_puede_ver_cualquier_solicitud_en_show()
    {
        $dueno = $this->crearUsuarioConRol(3); // Email unique auto
        $this->vincularEstudiante($dueno->id);

        $admin = $this->crearUsuarioConRol(5); // Email unique auto (antes fallaba aquí)

        $idSolicitud = DB::table('solicitudes')->insertGetId([
            'folio' => 'SOL-ADMIN',
            'user_id' => $dueno->id,
            'estado' => 'en proceso',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->actingAs($admin);
        $response = $this->getJson("api/solicitudes/{$idSolicitud}");
        
        $response->assertStatus(200)
                 ->assertJson(['folio' => 'SOL-ADMIN']);
    }

    #[Test]
    public function download_orden_de_pago_genera_pdf()
    {
        Pdf::shouldReceive('loadView')->andReturnSelf();
        Pdf::shouldReceive('output')->andReturn('PDF_BINARIO_FALSO');

        $user = $this->crearUsuarioConRol(3);
        $this->vincularEstudiante($user->id);

        $idSolicitud = DB::table('solicitudes')->insertGetId([
            'folio' => 'SOL-PDF',
            'user_id' => $user->id,
            'estado' => 'en proceso',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // CORRECCIÓN AQUÍ: Cambié 'solicitud_id' por 'idSolicitud' 
        // asumiendo que esa es tu llave foránea según la estructura de 'solicitudes'
        DB::table('ordenes_pago')->insert([
            'idSolicitud' => $idSolicitud, // <--- Verifica si en tu BD es 'solicitud_id' o 'idSolicitud'
            'montoTotal' => 500,
            'numeroCuentaDestino' => '123456',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        $this->actingAs($user);

        $response = $this->getJson("api/solicitudes/{$idSolicitud}/orden-de-pago");

        $response->assertStatus(200);
        $this->assertTrue($response->headers->contains('content-type', 'application/pdf'));
    }
}