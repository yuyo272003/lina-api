<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Tramite>
 */
class TramiteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // OJO: Si tu tabla no autoincrementa idTramite, descomenta la siguiente linea:
            // 'idTramite' => $this->faker->unique()->numberBetween(1, 1000),
            'nombreTramite' => $this->faker->sentence(3),
            'costoTramite' => $this->faker->randomFloat(2, 50, 500),
            'descripcionTramite' => $this->faker->paragraph,
        ];
    }
}