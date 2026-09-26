<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class RoomFacilityFactory extends Factory
{
    public function definition(): array
    {
        $name = 'Ruang ' . $this->faker->randomElement(['VVIP', 'VIP', 'Kelas 1', 'Kelas 2', 'Kelas 3', 'ICU', 'NICU', 'PICU']);
        return [
            'name' => $name,
            'slug' => Str::slug($name . '-' . Str::random(4)),
            'category' => $this->faker->randomElement(['premium', 'standard']),
            'tagline' => $this->faker->sentence(),
            'description' => '<p>' . implode('</p><p>', $this->faker->paragraphs(2)) . '</p>',
            'room_size' => '~' . $this->faker->numberBetween(15, 50) . ' m²',
            'bed_count' => $this->faker->numberBetween(1, 4) . ' Tempat Tidur',
            'max_companion' => 'Max ' . $this->faker->numberBetween(1, 2) . ' Penunggu',
            'image_path' => null,
            'amenities' => [
                ['group' => 'Fasilitas Medis', 'items' => ['Oksigen Sentral', 'Ners Call', 'Bed Electric']],
                ['group' => 'Fasilitas Umum', 'items' => ['AC', 'TV LED', 'Kamar Mandi Dalam', 'Sofa Bed']]
            ],
            'highlight_tags' => [
                ['icon' => 'heroicon-o-wifi', 'label' => 'Free Wi-Fi'],
                ['icon' => 'heroicon-o-tv', 'label' => 'Smart TV']
            ],
            'whatsapp_text' => 'Halo, saya ingin menanyakan ketersediaan kamar ' . $name,
            'sort_order' => $this->faker->numberBetween(1, 10),
            'is_active' => $this->faker->boolean(95),
        ];
    }
}
