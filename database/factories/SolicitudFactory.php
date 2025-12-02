<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Solicitud>
 */
class SolicitudFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // 'idSolicitud' => ...
            'user_id' => \App\Models\User::factory(),
            'folio' => 'SOL-' . $this->faker->unique()->numerify('########-######'),
            'estado' => 'en proceso',
            'rol_rechazo' => null,
            'observaciones' => null,
        ];
    }
}   