<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProgramaEducativo>
 */
class ProgramaEducativoFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array {
        return [
            'nombrePE' => $this->faker->jobTitle,
            'facultad_id' => \App\Models\Facultad::factory(),
        ];
    }
}
