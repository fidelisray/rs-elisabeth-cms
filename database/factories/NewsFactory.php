<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class NewsFactory extends Factory
{
    public function definition(): array
    {
        $title = $this->faker->sentence(6);
        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'author' => 'Tim Humas RS',
            'shorts' => $this->faker->text(200),
            'category' => $this->faker->randomElement(['Pengumuman', 'Kesehatan', 'Event', 'Teknologi Medis']),
            'content' => '<p>' . implode('</p><p>', $this->faker->paragraphs(4)) . '</p>',
            'image_path' => null,
            'is_published' => $this->faker->boolean(80),
        ];
    }
}
