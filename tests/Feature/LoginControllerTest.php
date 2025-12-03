<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Http\Controllers\Auth\LoginController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use PHPUnit\Framework\Attributes\Test;

class LoginControllerTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        DB::table('roles')->insert([
            ['IdRole' => 1, 'NombreRole' => 'Admin'],
            ['IdRole' => 2, 'NombreRole' => 'Academico'],
            ['IdRole' => 3, 'NombreRole' => 'Estudiante'],
            ['IdRole' => 4, 'NombreRole' => 'Egresado'],
        ]);
    }

    protected function invokeMethod($object, $methodName, array $parameters = [])
    {
        $reflection = new ReflectionMethod(get_class($object), $methodName);
        $reflection->setAccessible(true);
        return $reflection->invokeArgs($object, $parameters);
    }

    #[Test]
    public function el_metodo_get_email_genera_correos_correctamente()
    {
        $controller = new LoginController();

        $email = $this->invokeMethod($controller, 'getEmail', ['zs123456']);
        $this->assertEquals('zs123456@estudiantes.uv.mx', $email);

        $email = $this->invokeMethod($controller, 'getEmail', ['s123456']);
        $this->assertEquals('zs123456@estudiantes.uv.mx', $email);

        $email = $this->invokeMethod($controller, 'getEmail', ['juanperez']);
        $this->assertEquals('juanperez@uv.mx', $email);
    }

    #[Test]
    public function puede_crear_un_usuario_estudiante_desde_datos_de_graph()
    {
        $datosGraph = [
            'mail' => 'zs20001234@estudiantes.uv.mx',
            'first_name' => 'Pepito',
            'last_name' => 'Pérez',
            'matricula' => 'S20001234',
            'programa_educativo' => 'Licenciatura en Informática',
            'facultad' => 'Facultad de Contaduría y Administración',
            'campus' => 'Xalapa',
        ];

        $controller = new LoginController();
        $user = $this->invokeMethod($controller, 'createUser', [$datosGraph]);

        // Verificamos usuario
        $this->assertDatabaseHas('users', [
            'email' => 'zs20001234@estudiantes.uv.mx',
            'first_name' => 'Pepito',
        ]);

        // Verificamos relación en la tabla pivote role_usuario.
        // Asumo que aquí usas 'role_id' como clave foránea estándar hacia 'IdRole'
        $this->assertDatabaseHas('role_usuario', [
            'user_id' => $user->id,
            'role_id' => 3 // 3 = Estudiante
        ]);
    }

    #[Test]
    public function puede_crear_un_usuario_academico_desde_datos_de_graph()
    {
        $datosGraph = [
            'mail' => 'profesor@uv.mx',
            'first_name' => 'Dr. House',
            'last_name' => 'M.D.',
            'matricula' => '12345',
            'programa_educativo' => null,
            'facultad' => 'Facultad de Medicina',
            'campus' => 'Veracruz',
        ];

        $controller = new LoginController();
        $user = $this->invokeMethod($controller, 'createUser', [$datosGraph]);

        $this->assertDatabaseHas('role_usuario', [
            'user_id' => $user->id,
            'role_id' => 2 // 2 = Academico
        ]);

        $this->assertDatabaseHas('academicos', [
            'user_id' => $user->id,
            'NoPersonalAcademico' => '12345'
        ]);
    }
    
    #[Test]
    public function logout_funciona_correctamente()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Intenta la ruta estándar
        $response = $this->post('/logout'); 

        $this->assertGuest();
        $response->assertNoContent();
    }
}