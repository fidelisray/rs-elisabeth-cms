<?php

namespace Database\Factories;

use App\Models\OnlineLibrary;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OnlineLibraryFactory extends Factory
{
    protected $model = OnlineLibrary::class;

    public function definition(): array
    {
        $title = $this->faker->sentence(4);

        return [
            'title' => ['id' => $title, 'en' => $title.' (EN)'],
            'slug' => ['id' => Str::slug($title), 'en' => Str::slug($title.' en')],
            'description' => [
                'id' => '<p>'.$this->faker->paragraph(2).'</p>',
                'en' => '<p>'.$this->faker->paragraph(2).'</p>',
            ],
            'category' => [
                'id' => $this->faker->randomElement(['Panduan Medis', 'Edukasi Kesehatan', 'Brosur Pelayanan', 'Laporan Tahunan']),
                'en' => 'Medical Guidelines',
            ],
            'file_path' => 'online-libraries/sample.pdf',
            'file_name' => 'sample.pdf',
            'file_size' => 1048576, // 1 MB
            'is_published' => true,
        ];
    }
}
