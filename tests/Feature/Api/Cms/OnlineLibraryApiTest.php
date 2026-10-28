<?php

namespace Tests\Feature\Api\Cms;

use App\Models\OnlineLibrary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\GeneratesHmacHeaders;

class OnlineLibraryApiTest extends TestCase
{
    use GeneratesHmacHeaders, RefreshDatabase;

    public function test_api_returns_401_without_hmac_headers(): void
    {
        $response = $this->getJson('/api/v1/cms/online-libraries');
        $response->assertStatus(401);
    }

    public function test_api_returns_200_with_valid_hmac(): void
    {
        OnlineLibrary::create([
            'title' => ['id' => 'Buku Panduan Pasien', 'en' => 'Patient Guide Book'],
            'slug' => ['id' => 'buku-panduan-pasien', 'en' => 'patient-guide-book'],
            'description' => ['id' => 'Panduan lengkap untuk pasien', 'en' => 'Complete guide for patients'],
            'category' => ['id' => 'Edukasi', 'en' => 'Education'],
            'file_path' => 'online-libraries/panduan.pdf',
            'is_published' => true,
        ]);

        $response = $this->withHmac()->getJson('/api/v1/cms/online-libraries');
        $response->assertStatus(200)
            ->assertJsonPath('data.0.title', 'Buku Panduan Pasien')
            ->assertJsonPath('data.0.category', 'Edukasi');
    }

    public function test_api_returns_english_content_when_lang_param_is_en(): void
    {
        OnlineLibrary::create([
            'title' => ['id' => 'Buku Panduan Pasien', 'en' => 'Patient Guide Book'],
            'slug' => ['id' => 'buku-panduan-pasien', 'en' => 'patient-guide-book'],
            'description' => ['id' => 'Panduan lengkap untuk pasien', 'en' => 'Complete guide for patients'],
            'category' => ['id' => 'Edukasi', 'en' => 'Education'],
            'file_path' => 'online-libraries/panduan.pdf',
            'is_published' => true,
        ]);

        $response = $this->withHmac()->getJson('/api/v1/cms/online-libraries?lang=en');
        $response->assertStatus(200)
            ->assertJsonPath('data.0.title', 'Patient Guide Book')
            ->assertJsonPath('data.0.category', 'Education');
    }

    public function test_api_returns_detail_data(): void
    {
        OnlineLibrary::create([
            'title' => ['id' => 'Laporan Tahunan 2025', 'en' => 'Annual Report 2025'],
            'slug' => ['id' => 'laporan-tahunan-2025', 'en' => 'annual-report-2025'],
            'file_path' => 'online-libraries/annual-report-2025.pdf',
            'is_published' => true,
        ]);

        $response = $this->withHmac()->getJson('/api/v1/cms/online-libraries/laporan-tahunan-2025');
        $response->assertStatus(200)->assertJsonPath('data.title', 'Laporan Tahunan 2025');
    }
}
