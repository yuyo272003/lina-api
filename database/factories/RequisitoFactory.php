<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Requisito>
 */
class RequisitoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            // 'idRequisito' => $this->faker->unique()->numberBetween(1, 1000),
            'nombreRequisito' => 'Documento Test', // Valor por defecto
            'tipo' => 'documento', // documento o texto
            'descripcionRequisito' => $this->faker->sentence,
        ];
    }
}
