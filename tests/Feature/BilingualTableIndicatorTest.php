<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Resources\Articles\Pages\EditArticle;
use App\Filament\Resources\Articles\Pages\ListArticles;
use App\Filament\Resources\Doctors\Pages\ListDoctors;
use App\Filament\Resources\FacilityServices\Pages\ListFacilityServices;
use App\Filament\Resources\Feedbacks\Pages\ListFeedbacks;
use App\Filament\Resources\OnlineLibraries\Pages\ListOnlineLibraries;
use App\Filament\Resources\Promotions\Pages\ListPromotions;
use App\Filament\Support\BilingualTableColumns;
use App\Models\Article;
use App\Models\Doctor;
use App\Models\FacilityService;
use App\Models\Feedback;
use App\Models\OnlineLibrary;
use App\Models\Promotion;
use App\Models\RoomFacility;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\SeedsTestRolesAndUsers;
use Tests\TestCase;

class BilingualTableIndicatorTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTestRolesAndUsers;

    private User $superAdmin;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');

        $this->seedDefaultTestUsersAndRoles();
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);

        $this->superAdmin = User::where('email', 'admin@rs-elisabeth.com')->firstOrFail();
        $this->superAdmin->update(['must_change_password' => false]);
        $this->actingAs($this->superAdmin);

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_content_bilingual_evaluator_distinguishes_complete_and_partial_optional_fields(): void
    {
        $completeFacility = FacilityService::factory()->create([
            'wa_link_text' => ['id' => 'Hubungi WA', 'en' => 'Contact WA'],
            'wa_prefilled_message' => ['id' => 'Halo', 'en' => 'Hello'],
        ]);

        $partialFacility = FacilityService::factory()->create([
            'wa_link_text' => null,
            'wa_prefilled_message' => null,
        ]);

        $completeResult = BilingualTableColumns::evaluateContentTranslations(
            $completeFacility,
            ['name', 'slug', 'short_description', 'description'],
            ['wa_link_text', 'wa_prefilled_message'],
        );
        $partialResult = BilingualTableColumns::evaluateContentTranslations(
            $partialFacility,
            ['name', 'slug', 'short_description', 'description'],
            ['wa_link_text', 'wa_prefilled_message'],
        );

        $this->assertSame('complete', $completeResult['state']);
        $this->assertSame([], $completeResult['missing_optional']);

        $this->assertSame('partial', $partialResult['state']);
        $this->assertCount(2, $partialResult['missing_optional']);

        $partialRoom = RoomFacility::factory()->create(['whatsapp_text' => null]);
        $roomResult = BilingualTableColumns::evaluateContentTranslations(
            $partialRoom,
            ['name', 'slug', 'tagline', 'description'],
            ['whatsapp_text'],
        );
        $this->assertSame('partial', $roomResult['state']);

        Livewire::test(ListFacilityServices::class)
            ->assertCanSeeTableRecords([$completeFacility, $partialFacility])
            ->assertSee('ID · EN')
            ->assertSee('ID · EN (Parsial)');

        $partialLibrary = OnlineLibrary::create([
            'title' => ['id' => 'Panduan Pasien', 'en' => 'Patient Guide'],
            'slug' => ['id' => 'panduan-pasien', 'en' => 'patient-guide'],
            'category' => ['id' => 'Edukasi', 'en' => 'Education'],
            'description' => null,
            'file_path' => 'online-libraries/panduan.pdf',
            'is_published' => true,
        ]);

        Livewire::test(ListOnlineLibraries::class)
            ->assertCanSeeTableRecords([$partialLibrary])
            ->assertSee('ID · EN (Parsial)');
    }

    public function test_doctor_table_renders_concrete_bilingual_progress_badges(): void
    {
        $emptyDoctor = Doctor::create([
            'paramedic_code' => 'IND-DOC-001',
            'name' => 'dr. Kosong',
            'specialty_code' => 'KSM01',
            'specialty_name' => 'Penyakit Dalam',
            'service_unit_name' => 'Poli Dalam',
            'is_active' => true,
            'bio' => null,
            'location' => null,
            'experience' => null,
            'education' => null,
        ]);
        $partialDoctor = Doctor::create([
            'paramedic_code' => 'IND-DOC-002',
            'name' => 'dr. Parsial',
            'specialty_code' => 'KSM01',
            'specialty_name' => 'Penyakit Dalam',
            'service_unit_name' => 'Poli Dalam',
            'is_active' => true,
            'bio' => ['id' => 'Bio ID', 'en' => 'Bio EN'],
            'location' => ['id' => 'Poli A', 'en' => 'Clinic A'],
            'experience' => null,
            'education' => null,
        ]);
        $fullDoctor = Doctor::create([
            'paramedic_code' => 'IND-DOC-003',
            'name' => 'dr. Lengkap',
            'specialty_code' => 'KSM01',
            'specialty_name' => 'Penyakit Dalam',
            'service_unit_name' => 'Poli Dalam',
            'is_active' => true,
            'bio' => ['id' => 'Bio ID', 'en' => 'Bio EN'],
            'location' => ['id' => 'Poli B', 'en' => 'Clinic B'],
            'experience' => ['id' => '10 tahun', 'en' => '10 years'],
            'education' => ['id' => 'FK Undip', 'en' => 'Undip Med'],
        ]);

        // Cek evaluasi parsial valid DB (2/4) dan defensif in-memory asimetris (2/4 vs 1/4)
        $partialStats = BilingualTableColumns::evaluateDoctorProfile($partialDoctor);
        $this->assertSame(2, $partialStats['id_filled']);
        $this->assertSame(2, $partialStats['en_filled']);
        $this->assertCount(2, $partialStats['missing_id']);
        $this->assertCount(2, $partialStats['missing_en']);

        $asymmetricDoctor = new Doctor([
            'bio' => ['id' => 'Bio ID', 'en' => 'Bio EN'],
            'location' => ['id' => 'Poli A', 'en' => ''],
        ]);
        $asymmetricStats = BilingualTableColumns::evaluateDoctorProfile($asymmetricDoctor);
        $this->assertSame(2, $asymmetricStats['id_filled']);
        $this->assertSame(1, $asymmetricStats['en_filled']);
        $this->assertCount(3, $asymmetricStats['missing_en']);

        Livewire::test(ListDoctors::class)
            ->assertCanSeeTableRecords([$emptyDoctor, $partialDoctor, $fullDoctor])
            ->assertSee('ID 0/4 · EN 0/4')
            ->assertSee('ID 2/4 · EN 2/4')
            ->assertSee('ID 4/4 · EN 4/4');
    }

    public function test_click_through_filters_work_for_articles_promotions_and_feedback(): void
    {
        $publishedArticle = Article::factory()->create(['is_active' => true]);
        $draftArticle = Article::factory()->create(['is_active' => false]);

        Livewire::test(ListArticles::class)
            ->filterTable('is_active', false)
            ->assertCanSeeTableRecords([$draftArticle])
            ->assertCanNotSeeTableRecords([$publishedArticle]);

        $today = Carbon::parse(Promotion::today(), (string) config('app.timezone', 'Asia/Jakarta'));
        $expiringPromo = Promotion::factory()->create([
            'is_active' => true,
            'start_date' => $today->copy()->subDay()->toDateString(),
            'end_date' => $today->copy()->addDays(4)->toDateString(),
        ]);
        $longPromo = Promotion::factory()->create([
            'is_active' => true,
            'start_date' => $today->copy()->subDay()->toDateString(),
            'end_date' => $today->copy()->addDays(30)->toDateString(),
        ]);

        Livewire::test(ListPromotions::class)
            ->filterTable('expiring_soon', true)
            ->assertCanSeeTableRecords([$expiringPromo])
            ->assertCanNotSeeTableRecords([$longPromo]);

        $openFeedback = Feedback::factory()->create(['status' => 'baru']);
        $inProgressFeedback = Feedback::factory()->create(['status' => 'diproses']);
        $resolvedFeedback = Feedback::factory()->create(['status' => 'selesai']);

        Livewire::test(ListFeedbacks::class)
            ->filterTable('unresolved', true)
            ->assertCanSeeTableRecords([$openFeedback, $inProgressFeedback])
            ->assertCanNotSeeTableRecords([$resolvedFeedback]);
    }

    public function test_image_upload_forms_show_webp_helper_text_and_stored_format_status_on_edit(): void
    {
        $webpArticle = Article::factory()->create([
            'image_path' => 'articles/sample.webp',
        ]);

        Livewire::test(EditArticle::class, ['record' => $webpArticle->getRouteKey()])
            ->assertOk()
            ->assertSee('otomatis dioptimalkan ke format WebP saat disimpan')
            ->assertSee('Gambar tersimpan sudah berformat WebP teroptimasi.');

        $jpgArticle = Article::factory()->create([
            'image_path' => 'articles/legacy.jpg',
        ]);

        Livewire::test(EditArticle::class, ['record' => $jpgArticle->getRouteKey()])
            ->assertOk()
            ->assertSee('Format gambar saat ini (JPG) akan otomatis dikonversi ke WebP');
    }
}
