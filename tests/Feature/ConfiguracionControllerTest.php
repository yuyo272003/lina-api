<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Configuracion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;

class ConfiguracionControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // 1. Roles
        DB::table('roles')->insertOrIgnore([
            ['IdRole' => 3, 'NombreRole' => 'Estudiante'],
            ['IdRole' => 5, 'NombreRole' => 'Coordinador'],
        ]);

        // 2. Crear/Actualizar configuración inicial
        Configuracion::updateOrCreate(
            ['clave' => 'NUMERO_CUENTA_DESTINO'],
            [
                'valor' => '0000000000',
                'descripcion' => 'Cuenta inicial de prueba'
            ]
        );
    }

    // --- Helpers ---

    protected function crearCoordinador()
    {
        $user = User::factory()->create(['email' => 'coord_' . uniqid() . '@uv.mx']);
        DB::table('role_usuario')->insert(['user_id' => $user->id, 'role_id' => 5]);
        return $user;
    }

    protected function crearEstudiante()
    {
        $user = User::factory()->create(['email' => 'est_' . uniqid() . '@uv.mx']);
        DB::table('role_usuario')->insert(['user_id' => $user->id, 'role_id' => 3]);
        return $user;
    }

    // --- Tests ---

    #[Test]
    public function usuario_no_autorizado_no_puede_actualizar_cuenta()
    {
        $estudiante = $this->crearEstudiante();
        $this->actingAs($estudiante);

        // CORREGIDO: URL 'numero-cuenta'
        $response = $this->putJson('/api/configuracion/numero-cuenta', [
            'numero_cuenta' => '1234567890'
        ]);

        $response->assertStatus(403)
                 ->assertJson(['message' => 'No autorizado. Solo el rol de Coordinación principal puede actualizar el número de cuenta.']);
    }

    #[Test]
    public function coordinador_puede_actualizar_numero_cuenta()
    {
        $coordinador = $this->crearCoordinador();
        $this->actingAs($coordinador);

        $nuevaCuenta = '9876543210';

        // CORREGIDO: URL 'numero-cuenta'
        $response = $this->putJson('/api/configuracion/numero-cuenta', [
            'numero_cuenta' => $nuevaCuenta
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'message' => 'Número de cuenta GLOBAL actualizado con éxito.',
                     'numero_cuenta' => $nuevaCuenta
                 ]);

        $this->assertDatabaseHas('configuraciones', [
            'clave' => 'NUMERO_CUENTA_DESTINO',
            'valor' => $nuevaCuenta
        ]);
    }

    #[Test]
    public function valida_formato_de_numero_cuenta()
    {
        $coordinador = $this->crearCoordinador();
        $this->actingAs($coordinador);

        // CORREGIDO: URL 'numero-cuenta'
        $response = $this->putJson('/api/configuracion/numero-cuenta', [
            'numero_cuenta' => ''
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['numero_cuenta']);

        // CORREGIDO: URL 'numero-cuenta'
        $response = $this->putJson('/api/configuracion/numero-cuenta', [
            'numero_cuenta' => '12'
        ]);
        $response->assertStatus(422)->assertJsonValidationErrors(['numero_cuenta']);
    }

    #[Test]
    public function coordinador_puede_obtener_numero_cuenta()
    {
        $coordinador = $this->crearCoordinador();
        $this->actingAs($coordinador);

        // CORREGIDO: URL 'numero-cuenta'
        $response = $this->getJson('/api/configuracion/numero-cuenta');

        $response->assertStatus(200)
                 ->assertJsonStructure(['numero_cuenta']);
    }

    #[Test]
    public function estudiante_no_puede_ver_configuracion_cuenta()
    {
        $estudiante = $this->crearEstudiante();
        $this->actingAs($estudiante);

        // CORREGIDO: URL 'numero-cuenta'
        $response = $this->getJson('/api/configuracion/numero-cuenta');

        $response->assertStatus(403);
    }
}