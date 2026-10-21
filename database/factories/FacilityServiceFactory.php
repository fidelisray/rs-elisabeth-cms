<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FacilityServiceFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Fasilitas '.$this->faker->words(2, true);

        return [
            'name' => ['id' => $name, 'en' => $name.' (EN)'],
            'slug' => ['id' => Str::slug($name), 'en' => Str::slug($name.' en')],
            'description' => ['id' => '<p>'.implode('</p><p>', $this->faker->paragraphs(3)).'</p>', 'en' => '<p>'.implode('</p><p>', $this->faker->paragraphs(3)).'</p>'],
            'short_description' => ['id' => $this->faker->text(200), 'en' => $this->faker->text(200)],
            'image_path' => null,
            'category' => $this->faker->randomElement(['Layanan Medis', 'Layanan Penunjang', 'Layanan 24 Jam']),
            'highlights' => ['Layanan 24 Jam', 'Peralatan Modern', 'Tenaga Medis Profesional'],
            'wa_link_text' => ['id' => 'Hubungi Kami', 'en' => 'Contact Us'],
            'wa_prefilled_message' => ['id' => 'Halo, saya ingin informasi layanan.', 'en' => 'Hello, I would like service information.'],
            'wa_number' => '6281234567890',
            'has_appointment_cta' => true,
            'sort_order' => $this->faker->numberBetween(1, 10),
            'is_active' => $this->faker->boolean(90),
        ];
    }
}
