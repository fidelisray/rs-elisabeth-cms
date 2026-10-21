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
            'title' => ['id' => $title, 'en' => $title.' (EN)'],
            'slug' => ['id' => Str::slug($title), 'en' => Str::slug($title.' en')],
            'author' => 'Tim Marketing RS',
            'shorts' => ['id' => $this->faker->text(200), 'en' => $this->faker->text(200)],
            'category' => ['id' => $this->faker->randomElement(['Pengumuman', 'Kesehatan', 'Event', 'Teknologi Medis']), 'en' => 'Announcement'],
            'content' => ['id' => '<p>'.implode('</p><p>', $this->faker->paragraphs(4)).'</p>', 'en' => '<p>'.implode('</p><p>', $this->faker->paragraphs(4)).'</p>'],
            'image_path' => null,
            'is_published' => $this->faker->boolean(80),
        ];
    }
}
