<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PromotionFactory extends Factory
{
    public function definition(): array
    {
        $title = 'Promo ' . $this->faker->words(3, true);
        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'shorts' => $this->faker->text(200),
            'description' => '<p>' . implode('</p><p>', $this->faker->paragraphs(3)) . '</p>',
            'image_path' => null,
            'start_date' => $this->faker->dateTimeBetween('-1 month', 'now')->format('Y-m-d'),
            'end_date' => $this->faker->dateTimeBetween('+1 week', '+3 months')->format('Y-m-d'),
            'is_active' => $this->faker->boolean(90),
        ];
    }
}
