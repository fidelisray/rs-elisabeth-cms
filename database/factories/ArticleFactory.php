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
            'title' => ['id' => $title, 'en' => $title.' (EN)'],
            'slug' => ['id' => Str::slug($title), 'en' => Str::slug($title.' en')],
            'image_path' => null,
            'shorts' => ['id' => $this->faker->text(200), 'en' => $this->faker->text(200)],
            'content' => ['id' => '<p>'.implode('</p><p>', $this->faker->paragraphs(5)).'</p>', 'en' => '<p>'.implode('</p><p>', $this->faker->paragraphs(5)).'</p>'],
            'tags' => ['id' => implode(',', $this->faker->words(3)), 'en' => implode(',', $this->faker->words(3))],
            'author' => 'dr. '.$this->faker->lastName(),
            'is_active' => 'yes',
            'views' => $this->faker->numberBetween(10, 500),
        ];
    }
}
