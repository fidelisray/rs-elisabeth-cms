<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FacilityServiceFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Fasilitas ' . $this->faker->words(2, true);
        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => '<p>' . implode('</p><p>', $this->faker->paragraphs(3)) . '</p>',
            'short_description' => $this->faker->text(200),
            'image_path' => null,
            'category' => $this->faker->randomElement(['Medis', 'Penunjang Medis', 'Umum']),
            'highlights' => ['Layanan 24 Jam', 'Peralatan Modern', 'Tenaga Medis Profesional'],
            'wa_link_text' => 'Hubungi Kami',
            'wa_number' => '6281234567890',
            'has_appointment_cta' => true,
            'sort_order' => $this->faker->numberBetween(1, 10),
            'is_active' => $this->faker->boolean(90),
        ];
    }
}
