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
                ['group' => ['id' => 'Fasilitas Medis', 'en' => 'Medical Facilities'], 'items' => [
                    ['name' => ['id' => 'Oksigen Sentral', 'en' => 'Central Oxygen'], 'value' => null, 'show_in_comparison' => false],
                    ['name' => ['id' => 'Panggilan Perawat', 'en' => 'Nurse Call'], 'value' => null, 'show_in_comparison' => false],
                ]],
                ['group' => ['id' => 'Fasilitas Umum', 'en' => 'General Facilities'], 'items' => [
                    ['name' => ['id' => 'AC', 'en' => 'Air Conditioning'], 'value' => null, 'show_in_comparison' => true],
                    ['name' => ['id' => 'Televisi', 'en' => 'Television'], 'value' => null, 'show_in_comparison' => false],
                ]],
            ],
            'highlight_tags' => [
                ['icon' => 'fa-solid fa-wifi', 'label' => ['id' => 'Wi-Fi Gratis', 'en' => 'Free Wi-Fi']],
                ['icon' => 'fa-solid fa-tv', 'label' => ['id' => 'TV Pintar', 'en' => 'Smart TV']],
            ],
            'whatsapp_text' => ['id' => 'Halo, saya ingin menanyakan ketersediaan kamar '.$name, 'en' => 'Hello, I would like to check room availability.'],
            'sort_order' => $this->faker->numberBetween(1, 10),
            'is_active' => $this->faker->boolean(95),
        ];
    }
}
