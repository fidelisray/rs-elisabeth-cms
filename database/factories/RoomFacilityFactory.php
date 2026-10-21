<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RoomFacilityFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Ruang '.$this->faker->randomElement(['VVIP', 'VIP', 'Kelas 1', 'Kelas 2', 'Kelas 3', 'ICU', 'NICU', 'PICU']);

        return [
            'name' => ['id' => $name, 'en' => $name.' (EN)'],
            'slug' => ['id' => Str::slug($name.'-'.Str::random(4)), 'en' => Str::slug($name.' en-'.Str::random(4))],
            'tagline' => ['id' => $this->faker->sentence(), 'en' => $this->faker->sentence()],
            'description' => ['id' => '<p>'.implode('</p><p>', $this->faker->paragraphs(2)).'</p>', 'en' => '<p>'.implode('</p><p>', $this->faker->paragraphs(2)).'</p>'],
            'room_size' => $this->faker->numberBetween(15, 50),
            'bed_count' => $this->faker->numberBetween(1, 4),
            'max_companion' => $this->faker->numberBetween(1, 2),
            'image_path' => null,
            'amenities' => [
                ['group' => 'Fasilitas Medis', 'items' => [
                    ['name' => 'Oksigen Sentral', 'value' => null, 'show_in_comparison' => false],
                    ['name' => 'Ners Call', 'value' => null, 'show_in_comparison' => false],
                ]],
                ['group' => 'Fasilitas Umum', 'items' => [
                    ['name' => 'AC', 'value' => null, 'show_in_comparison' => true],
                    ['name' => 'TV LED', 'value' => null, 'show_in_comparison' => false],
                ]],
            ],
            'highlight_tags' => [
                ['icon' => 'fa-solid fa-wifi', 'label' => 'Free Wi-Fi'],
                ['icon' => 'fa-solid fa-tv', 'label' => 'Smart TV'],
            ],
            'whatsapp_text' => 'Halo, saya ingin menanyakan ketersediaan kamar '.$name,
            'sort_order' => $this->faker->numberBetween(1, 10),
            'is_active' => $this->faker->boolean(95),
        ];
    }
}
