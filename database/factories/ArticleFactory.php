<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class ArticleFactory extends Factory
{
    public function definition(): array
    {
        $title = $this->faker->sentence(8);
        return [
            'judul' => $title,
            'slug' => Str::slug($title),
            'image_path' => null,
            'shorts' => $this->faker->text(200),
            'content' => '<p>' . implode('</p><p>', $this->faker->paragraphs(5)) . '</p>',
            'tags' => implode(',', $this->faker->words(3)),
            'author' => 'dr. ' . $this->faker->lastName(),
            'is_active' => 'yes',
            'views' => $this->faker->numberBetween(10, 500),
        ];
    }
}
