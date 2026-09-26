<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class BannerPromotionFactory extends Factory
{
    public function definition(): array
    {
        $title = 'Banner ' . $this->faker->words(3, true);
        return [
            'title' => $title,
            'slug' => Str::slug($title),
            // Dummy image URL is not allowed directly for path if using specific disk logic, 
            // but we'll put a placeholder string for now.
            'image_path' => 'banners/placeholder-' . $this->faker->numberBetween(1, 10) . '.jpg', 
            'is_active' => $this->faker->boolean(90),
            'sort_order' => $this->faker->numberBetween(1, 5),
        ];
    }
}
