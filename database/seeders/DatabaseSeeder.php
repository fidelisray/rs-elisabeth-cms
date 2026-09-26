<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\News;
use App\Models\Promotion;
use App\Models\Article;
use App\Models\FacilityService;
use App\Models\RoomFacility;
use App\Models\BannerPromotion;

class DatabaseSeeder extends Seeder
{
    // Hapus WithoutModelEvents agar Observer (created_by/updated_by) tetap berjalan
    // use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            AdminUserSeeder::class,
        ]);

        News::factory(10)->create();
        Promotion::factory(5)->create();
        Article::factory(15)->create();
        FacilityService::factory(8)->create();
        RoomFacility::factory(12)->create();
        BannerPromotion::factory(3)->create();
    }
}
