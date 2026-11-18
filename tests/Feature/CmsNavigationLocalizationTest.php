<?php

namespace Tests\Feature;

use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\BannerPromotions\BannerPromotionResource;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Filament\Resources\FacilityServices\FacilityServiceResource;
use App\Filament\Resources\Feedbacks\FeedbackResource;
use App\Filament\Resources\News\NewsResource;
use App\Filament\Resources\OnlineLibraries\OnlineLibraryResource;
use App\Filament\Resources\Promotions\PromotionResource;
use App\Filament\Resources\Questionnaires\QuestionnaireResource;
use App\Filament\Resources\RoomFacilities\RoomFacilityResource;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Widgets\FeedbackSectionWidget;
use App\Filament\Widgets\QuestionnaireSectionWidget;
use App\Models\Article;
use App\Models\BannerPromotion;
use App\Models\Doctor;
use App\Models\FacilityService;
use App\Models\Feedback;
use App\Models\News;
use App\Models\OnlineLibrary;
use App\Models\Promotion;
use App\Models\QuestionnaireResponse;
use App\Models\Role;
use App\Models\RoomFacility;
use App\Models\User;
use BezhanSalleh\FilamentShield\Resources\Roles\RoleResource;
use Database\Seeders\DatabaseSeeder;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class CmsNavigationLocalizationTest extends TestCase
{
    use RefreshDatabase;

    private User $superAdmin;

    private User $marketing;

    private User $staffIt;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(DatabaseSeeder::class);
        $this->artisan('app:super-admin:init', ['--email' => 'admin@rs-elisabeth.com'])->assertExitCode(0);

        $this->superAdmin = User::where('email', 'admin@rs-elisabeth.com')->firstOrFail();
        $this->marketing = User::where('email', 'marketing@rs-elisabeth.com')->firstOrFail();
        $this->staffIt = User::where('email', 'it@rs-elisabeth.com')->firstOrFail();

        foreach ([$this->superAdmin, $this->marketing, $this->staffIt] as $user) {
            $user->update(['must_change_password' => false]);
        }

        Filament::setCurrentPanel(Filament::getPanel('admin'));
    }

    public function test_locale_configuration_and_shield_permission_keys_remain_unchanged(): void
    {
        $this->assertSame('id', config('app.locale'));
        $this->assertSame('id', config('app.fallback_locale'));

        foreach ([
            'ViewAny:Article',
            'ViewAny:BannerPromotion',
            'ViewAny:Doctor',
            'ViewAny:FacilityService',
            'ViewAny:Feedback',
            'ViewAny:RoomFacility',
            'ViewAny:News',
            'ViewAny:OnlineLibrary',
            'ViewAny:Promotion',
            'ViewAny:QuestionnaireResponse',
            'ViewAny:User',
            'ViewAny:Role',
        ] as $permission) {
            $this->assertTrue(
                Permission::where('name', $permission)->exists(),
                "Expected Shield permission [{$permission}] to remain intact."
            );
        }

        $this->assertFalse(Permission::where('name', 'View:Dashboard')->exists());
    }

    public function test_resource_navigation_plural_and_singular_labels_match_standard(): void
    {
        $expected = [
            ArticleResource::class => [
                'nav' => 'Articles',
                'plural' => 'Articles',
                'singular' => 'Article',
                'group' => null,
                'sort' => 1,
            ],
            BannerPromotionResource::class => [
                'nav' => 'Banner Promotions',
                'plural' => 'Banner Promotions',
                'singular' => 'Banner Promotion',
                'group' => null,
                'sort' => 2,
            ],
            DoctorResource::class => [
                'nav' => 'Doctor Profiles',
                'plural' => 'Doctor Profiles',
                'singular' => 'Doctor Profile',
                'group' => null,
                'sort' => 3,
            ],
            FacilityServiceResource::class => [
                'nav' => 'Facilities & Services',
                'plural' => 'Facilities & Services',
                'singular' => 'Facility & Service',
                'group' => null,
                'sort' => 4,
            ],
            FeedbackResource::class => [
                'nav' => 'Feedback & Suggestions',
                'plural' => 'Feedback & Suggestions',
                'singular' => 'Feedback',
                'group' => null,
                'sort' => 5,
            ],
            RoomFacilityResource::class => [
                'nav' => 'Inpatient Rooms',
                'plural' => 'Inpatient Rooms',
                'singular' => 'Inpatient Room',
                'group' => null,
                'sort' => 6,
            ],
            NewsResource::class => [
                'nav' => 'News',
                'plural' => 'News',
                'singular' => 'News',
                'group' => null,
                'sort' => 7,
            ],
            OnlineLibraryResource::class => [
                'nav' => 'Online Library',
                'plural' => 'Online Library',
                'singular' => 'Online Library',
                'group' => null,
                'sort' => 8,
            ],
            PromotionResource::class => [
                'nav' => 'Promotions',
                'plural' => 'Promotions',
                'singular' => 'Promotion',
                'group' => null,
                'sort' => 9,
            ],
            QuestionnaireResource::class => [
                'nav' => 'Satisfaction Questionnaires',
                'plural' => 'Satisfaction Questionnaires',
                'singular' => 'Satisfaction Questionnaire',
                'group' => null,
                'sort' => 10,
            ],
            UserResource::class => [
                'nav' => 'User Management',
                'plural' => 'Users',
                'singular' => 'User',
                'group' => 'Administration',
                'sort' => 1,
            ],
            RoleResource::class => [
                'nav' => 'Roles',
                'plural' => 'Roles',
                'singular' => 'Role',
                'group' => 'Administration',
                'sort' => 2,
            ],
        ];

        $this->actingAs($this->superAdmin);

        $this->assertSame('Dashboard', Dashboard::getNavigationLabel());
        $this->assertSame('Dashboard', (new Dashboard)->getTitle());

        foreach ($expected as $resource => $labels) {
            $this->assertSame($labels['nav'], $resource::getNavigationLabel(), "{$resource} navigationLabel mismatch");
            $this->assertSame($labels['plural'], $resource::getTitleCasePluralModelLabel(), "{$resource} pluralModelLabel mismatch");
            $this->assertSame($labels['singular'], $resource::getTitleCaseModelLabel(), "{$resource} modelLabel mismatch");
            $this->assertSame($labels['group'], $resource::getNavigationGroup(), "{$resource} navigationGroup mismatch");
            $this->assertSame($labels['sort'], $resource::getNavigationSort(), "{$resource} navigationSort mismatch");
        }
    }

    public function test_super_admin_sidebar_navigation_groups_labels_and_alphabetical_order(): void
    {
        $this->actingAs($this->superAdmin);

        $panel = Filament::getPanel('admin');
        $groups = array_values($panel->getNavigation());

        $this->assertCount(2, $groups);

        /** @var NavigationGroup $topLevelGroup */
        $topLevelGroup = $groups[0];
        $this->assertNull($topLevelGroup->getLabel());

        $topLevelLabels = collect($topLevelGroup->getItems())
            ->map(fn (NavigationItem $item): string => $item->getLabel())
            ->values()
            ->all();

        $this->assertSame([
            'Dashboard',
            'Articles',
            'Banner Promotions',
            'Doctor Profiles',
            'Facilities & Services',
            'Feedback & Suggestions',
            'Inpatient Rooms',
            'News',
            'Online Library',
            'Promotions',
            'Satisfaction Questionnaires',
        ], $topLevelLabels);

        /** @var NavigationGroup $adminGroup */
        $adminGroup = $groups[1];
        $this->assertSame('Administration', $adminGroup->getLabel());

        $adminLabels = collect($adminGroup->getItems())
            ->map(fn (NavigationItem $item): string => $item->getLabel())
            ->values()
            ->all();

        $this->assertSame([
            'User Management',
            'Roles',
        ], $adminLabels);

        $response = $this->get('/admin')->assertOk();
        $html = $response->getContent();
        $this->assertSame('Dashboard', $this->extractPageHeading($html));
        $this->assertSame($this->expectedDocumentTitle('Dashboard'), $this->extractPageTitle($html));

        $response->assertSeeInOrder([
            'Dashboard',
            'Articles',
            'Banner Promotions',
            'Doctor Profiles',
            'Facilities & Services',
            'Feedback & Suggestions',
            'Inpatient Rooms',
            'News',
            'Online Library',
            'Promotions',
            'Satisfaction Questionnaires',
            'Administration',
            'User Management',
            'Roles',
        ]);

        foreach ([
            'Dasbor',
            'Profil Dokter',
            'Facility Services',
            'Facilities And Services',
            'Room Facilities',
            'Perpustakaan Online',
            'Manajemen User',
            'Administrasi',
            'Filament Shield',
            'Peran',
            'Daftar Peran',
        ] as $forbidden) {
            $response->assertDontSee($forbidden);
        }

        $sidebarText = $this->extractSidebarNavigationText($html);
        foreach ([
            'Dasbor',
            'Profil Dokter',
            'Facility Services',
            'Room Facilities',
            'Facilities And Services',
            'Perpustakaan Online',
            'Kuesioner Kepuasan',
            'Kritik & Saran',
            'Manajemen User',
            'Administrasi',
            'Filament Shield',
            'Peran',
            'Daftar Peran',
        ] as $forbiddenSidebarLabel) {
            $this->assertStringNotContainsString($forbiddenSidebarLabel, $sidebarText);
        }
        $this->assertStringNotContainsStringIgnoringCase('peran', $sidebarText);
    }

    public function test_dashboard_remains_accessible_for_all_authenticated_panel_roles_without_extra_permissions(): void
    {
        $panelOnlyRole = Role::findOrCreate('panel_user', 'web');
        $panelOnlyUser = User::factory()->create(['must_change_password' => false]);
        $panelOnlyUser->assignRole($panelOnlyRole);

        foreach ([$this->marketing, $this->staffIt, $panelOnlyUser] as $user) {
            session()->flush();
            $this->actingAs($user);

            $panel = Filament::getPanel('admin');
            $groups = array_values($panel->getNavigation());
            $groupLabels = collect($groups)->map(fn (NavigationGroup $group): ?string => $group->getLabel())->all();

            $this->assertNotContains('Administration', $groupLabels);
            $this->assertNotContains('Filament Shield', $groupLabels);

            $response = $this->get('/admin')->assertOk();
            $html = $response->getContent();
            $this->assertSame('Dashboard', $this->extractPageHeading($html));
            $this->assertSame($this->expectedDocumentTitle('Dashboard'), $this->extractPageTitle($html));
            $response->assertDontSee('Peran');
            $response->assertDontSee('Daftar Peran');
            $response->assertDontSee('Dasbor');
        }
    }

    public function test_list_create_edit_and_view_page_headings_use_consistent_english_and_clean_ampersands(): void
    {
        $this->actingAs($this->superAdmin);

        $article = Article::create([
            'title' => ['id' => 'Artikel Uji', 'en' => 'Test Article'],
            'slug' => ['id' => 'artikel-uji', 'en' => 'test-article'],
            'content' => ['id' => 'Isi artikel.', 'en' => 'Article content.'],
        ]);
        $banner = BannerPromotion::create([
            'title' => ['id' => 'Banner Uji', 'en' => 'Test Banner'],
            'slug' => ['id' => 'banner-uji', 'en' => 'test-banner'],
            'image_path' => 'banners/test.jpg',
        ]);
        $doctor = Doctor::create([
            'paramedic_code' => 'DOC-NAV-1',
            'name' => 'dr. Test Navigation',
            'specialty_code' => 'SP-NAV',
            'specialty_name' => 'Spesialis Umum',
            'is_active' => true,
        ]);
        $facility = FacilityService::factory()->create([
            'name' => ['id' => 'Layanan IGD', 'en' => 'ER Service'],
            'slug' => ['id' => 'layanan-igd', 'en' => 'er-service'],
        ]);
        $facilityWithAmpersand = FacilityService::factory()->create([
            'name' => ['id' => 'Layanan Ibu & Anak', 'en' => 'Maternity & Child Service'],
            'slug' => ['id' => 'layanan-ibu-anak', 'en' => 'maternity-child-service'],
        ]);
        $feedback = Feedback::factory()->create(['ticket_number' => 'FB-NAV-001']);
        $room = RoomFacility::create([
            'name' => ['id' => 'Ruang Anggrek', 'en' => 'Orchid Room'],
            'slug' => ['id' => 'ruang-anggrek', 'en' => 'orchid-room'],
            'tagline' => ['id' => 'Nyaman', 'en' => 'Comfortable'],
            'description' => ['id' => 'Deskripsi kamar', 'en' => 'Room description'],
        ]);
        $news = News::factory()->create([
            'title' => ['id' => 'Berita Uji', 'en' => 'Test News'],
            'slug' => ['id' => 'berita-uji', 'en' => 'test-news'],
        ]);
        $library = OnlineLibrary::factory()->create([
            'title' => ['id' => 'Dokumen Uji', 'en' => 'Test Document'],
            'slug' => ['id' => 'dokumen-uji', 'en' => 'test-document'],
        ]);
        $promotion = Promotion::create([
            'title' => ['id' => 'Promo Uji', 'en' => 'Test Promo'],
            'slug' => ['id' => 'promo-uji', 'en' => 'test-promo'],
            'description' => ['id' => 'Deskripsi promo', 'en' => 'Promo description'],
        ]);
        $questionnaire = QuestionnaireResponse::factory()->create();
        $role = Role::findByName('marketing', 'web');

        $listExpectations = [
            ArticleResource::getUrl('index') => 'Articles',
            BannerPromotionResource::getUrl('index') => 'Banner Promotions',
            DoctorResource::getUrl('index') => 'Doctor Profiles',
            FacilityServiceResource::getUrl('index') => 'Facilities & Services',
            FeedbackResource::getUrl('index') => 'Feedback & Suggestions',
            RoomFacilityResource::getUrl('index') => 'Inpatient Rooms',
            NewsResource::getUrl('index') => 'News',
            OnlineLibraryResource::getUrl('index') => 'Online Library',
            PromotionResource::getUrl('index') => 'Promotions',
            QuestionnaireResource::getUrl('index') => 'Satisfaction Questionnaires',
            UserResource::getUrl('index') => 'Users',
            RoleResource::getUrl('index') => 'Roles',
        ];

        foreach ($listExpectations as $url => $expectedHeading) {
            $response = $this->get($url)->assertOk();
            $html = $response->getContent();
            $heading = $this->extractPageHeading($html);
            $title = $this->extractPageTitle($html);
            $documentPageTitle = $this->extractDocumentPageTitle($html);
            $this->assertSame($expectedHeading, $heading, "List heading mismatch at [{$url}]");
            $this->assertSame($this->expectedDocumentTitle($expectedHeading), $title, "List <title> mismatch at [{$url}]");
            $this->assertStringNotContainsString('And', $heading, "Unexpected capitalized 'And' in [{$heading}]");
            $this->assertStringNotContainsString('And', $documentPageTitle, "Unexpected capitalized 'And' in <title> [{$title}]");
        }

        $createExpectations = [
            ArticleResource::getUrl('create') => 'Create Article',
            BannerPromotionResource::getUrl('create') => 'Create Banner Promotion',
            FacilityServiceResource::getUrl('create') => 'Create Facility & Service',
            RoomFacilityResource::getUrl('create') => 'Create Inpatient Room',
            NewsResource::getUrl('create') => 'Create News',
            OnlineLibraryResource::getUrl('create') => 'Create Online Library',
            PromotionResource::getUrl('create') => 'Create Promotion',
            UserResource::getUrl('create') => 'Create User',
            RoleResource::getUrl('create') => 'Create Role',
        ];

        foreach ($createExpectations as $url => $expectedHeading) {
            $response = $this->get($url)->assertOk();
            $html = $response->getContent();
            $heading = $this->extractPageHeading($html);
            $title = $this->extractPageTitle($html);
            $documentPageTitle = $this->extractDocumentPageTitle($html);
            $this->assertSame($expectedHeading, $heading, "Create heading mismatch at [{$url}]");
            $this->assertSame($this->expectedDocumentTitle($expectedHeading), $title, "Create <title> mismatch at [{$url}]");
            $this->assertStringNotContainsString('Buat ', $heading);
            $this->assertStringNotContainsString('Buat ', $documentPageTitle);
            $this->assertStringNotContainsString('And', $heading);
            $this->assertStringNotContainsString('And', $documentPageTitle);
        }

        $editAndViewExpectations = [
            ArticleResource::getUrl('edit', ['record' => $article]) => 'Edit Artikel Uji',
            BannerPromotionResource::getUrl('edit', ['record' => $banner]) => 'Edit Banner Uji',
            DoctorResource::getUrl('edit', ['record' => $doctor]) => 'Edit dr. Test Navigation',
            FacilityServiceResource::getUrl('edit', ['record' => $facility]) => 'Edit Layanan IGD',
            FacilityServiceResource::getUrl('edit', ['record' => $facilityWithAmpersand]) => 'Edit Layanan Ibu & Anak',
            RoomFacilityResource::getUrl('edit', ['record' => $room]) => 'Edit Ruang Anggrek',
            NewsResource::getUrl('edit', ['record' => $news]) => 'Edit Berita Uji',
            OnlineLibraryResource::getUrl('edit', ['record' => $library]) => 'Edit Dokumen Uji',
            PromotionResource::getUrl('edit', ['record' => $promotion]) => 'Edit Promo Uji',
            UserResource::getUrl('edit', ['record' => $this->marketing]) => 'Edit '.$this->marketing->name,
            RoleResource::getUrl('edit', ['record' => $role]) => 'Edit marketing',
            RoleResource::getUrl('view', ['record' => $role]) => 'View marketing',
            FeedbackResource::getUrl('view', ['record' => $feedback]) => 'View FB-NAV-001',
            QuestionnaireResource::getUrl('view', ['record' => $questionnaire]) => 'View Satisfaction Questionnaire',
        ];

        foreach ($editAndViewExpectations as $url => $expectedHeading) {
            $response = $this->get($url)->assertOk();
            $html = $response->getContent();
            $heading = $this->extractPageHeading($html);
            $title = $this->extractPageTitle($html);
            $documentPageTitle = $this->extractDocumentPageTitle($html);
            $this->assertSame($expectedHeading, $heading, "Edit/View heading mismatch at [{$url}]");
            $this->assertSame($this->expectedDocumentTitle($expectedHeading), $title, "Edit/View <title> mismatch at [{$url}]");
            $this->assertStringNotContainsString('Ubah ', $heading);
            $this->assertStringNotContainsString('Ubah ', $documentPageTitle);
            $this->assertStringNotContainsString('Lihat ', $heading);
            $this->assertStringNotContainsString('Lihat ', $documentPageTitle);
        }
    }

    public function test_facility_service_edit_page_preserves_ampersand_in_heading_and_title(): void
    {
        $this->actingAs($this->superAdmin);

        $facility = FacilityService::factory()->create([
            'name' => ['id' => 'Layanan Ibu & Anak', 'en' => 'Maternity & Child Service'],
            'slug' => ['id' => 'layanan-ibu-anak', 'en' => 'maternity-child-service'],
        ]);

        $response = $this->get(FacilityServiceResource::getUrl('edit', ['record' => $facility]))->assertOk();
        $html = $response->getContent();
        $heading = $this->extractPageHeading($html);
        $title = $this->extractPageTitle($html);
        $documentPageTitle = $this->extractDocumentPageTitle($html);

        $this->assertSame('Edit Layanan Ibu & Anak', $heading);
        $this->assertSame($this->expectedDocumentTitle('Edit Layanan Ibu & Anak'), $title);
        $this->assertSame('Edit Layanan Ibu & Anak', $documentPageTitle);
        $this->assertStringContainsString('&', $heading);
        $this->assertStringContainsString('&', $documentPageTitle);
        $this->assertStringNotContainsString('And', $heading);
        $this->assertStringNotContainsString('And', $documentPageTitle);
        $this->assertMatchesRegularExpression('/<h1[^>]*class="[^"]*fi-header-heading[^"]*"[^>]*>[^<]*Edit Layanan Ibu &amp; Anak[^<]*<\/h1>/s', $html);
        $this->assertMatchesRegularExpression('/<title[^>]*>[^<]*Edit Layanan Ibu &amp; Anak/i', $html);
        $this->assertDoesNotMatchRegularExpression('/<h1[^>]*class="[^"]*fi-header-heading[^"]*"[^>]*>.*?&amp;amp;.*?<\/h1>/s', $html);
        $this->assertDoesNotMatchRegularExpression('/<title[^>]*>.*?&amp;amp;.*?<\/title>/si', $html);

        $response
            ->assertDontSee('Edit Layanan Ibu And Anak')
            ->assertDontSee('Layanan Ibu And Anak')
            ->assertDontSee('Ubah Layanan Ibu & Anak');
    }

    public function test_shield_role_create_page_renders_english_resource_permission_section_labels(): void
    {
        $this->actingAs($this->superAdmin);

        $this->assertStringEndsWith('/admin/shield/roles/create', RoleResource::getUrl('create'));

        $response = $this->get('/admin/shield/roles/create')->assertOk();
        $html = $response->getContent();

        $this->assertSame('Create Role', $this->extractPageHeading($html));
        $this->assertSame($this->expectedDocumentTitle('Create Role'), $this->extractPageTitle($html));

        $sectionHeadings = $this->extractSectionHeadings($html);
        $expectedSectionLabels = [
            'Article',
            'Banner Promotion',
            'Doctor Profile',
            'Facility & Service',
            'Feedback',
            'Inpatient Room',
            'News',
            'Online Library',
            'Promotion',
            'Role',
            'Satisfaction Questionnaire',
            'User',
        ];
        $this->assertCount(count($expectedSectionLabels), $sectionHeadings, 'Unexpected Shield resource permission section count on /admin/shield/roles/create.');

        foreach ($expectedSectionLabels as $expectedSectionLabel) {
            $response->assertSee($expectedSectionLabel);
            $this->assertContains($expectedSectionLabel, $sectionHeadings, "Missing Shield section heading [{$expectedSectionLabel}].");
        }

        foreach ($sectionHeadings as $sectionHeading) {
            $this->assertStringNotContainsString('And', $sectionHeading, "Unexpected capitalized 'And' in Shield section [{$sectionHeading}].");
            $this->assertStringNotContainsStringIgnoringCase('peran', $sectionHeading, "Unexpected Indonesian 'Peran' in Shield section [{$sectionHeading}].");
            $this->assertNotSame('Facility Service', $sectionHeading);
            $this->assertNotSame('Room Facility', $sectionHeading);
            $this->assertNotSame('Profil Dokter', $sectionHeading);
            $this->assertNotSame('Kuesioner', $sectionHeading);
            $this->assertNotSame('Kuesioner Kepuasan', $sectionHeading);
            $this->assertNotSame('Kritik & Saran', $sectionHeading);
            $this->assertNotSame('Perpustakaan Online', $sectionHeading);
        }

        $sidebarText = $this->extractSidebarNavigationText($html);
        $this->assertStringNotContainsStringIgnoringCase('peran', $sidebarText);
        $this->assertStringNotContainsString('Filament Shield', $sidebarText);

        foreach ([
            'Peran',
            'Daftar Peran',
            'Buat Peran',
            'Facility And Service',
            'Facilities And Services',
            'Room Facility',
            'Facility Service',
            'Profil Dokter',
            'Kuesioner Kepuasan',
            'Filament Shield',
        ] as $forbidden) {
            $response->assertDontSee($forbidden);
        }
    }

    public function test_document_titles_and_absence_of_stale_indonesian_menu_labels_on_dashboard_and_questionnaires(): void
    {
        $this->actingAs($this->superAdmin);

        $dashboardResponse = $this->get('/admin')->assertOk();
        $dashboardHtml = $dashboardResponse->getContent();
        $this->assertSame('Dashboard', $this->extractPageHeading($dashboardHtml));
        $this->assertSame(
            $this->expectedDocumentTitle('Dashboard'),
            $this->extractPageTitle($dashboardHtml)
        );
        $dashboardSidebarText = $this->extractSidebarNavigationText($dashboardHtml);
        $this->assertStringContainsString('Roles', $dashboardSidebarText);
        $this->assertStringNotContainsStringIgnoringCase('peran', $dashboardSidebarText);
        $this->assertStringNotContainsString('Dasbor', $dashboardSidebarText);
        $dashboardResponse
            ->assertDontSee('Peran')
            ->assertDontSee('Daftar Peran')
            ->assertDontSee('Dasbor');

        $questionnaireResponse = $this->get('/admin/questionnaires')->assertOk();
        $questionnaireHtml = $questionnaireResponse->getContent();
        $this->assertSame('Satisfaction Questionnaires', $this->extractPageHeading($questionnaireHtml));
        $this->assertSame(
            $this->expectedDocumentTitle('Satisfaction Questionnaires'),
            $this->extractPageTitle($questionnaireHtml)
        );
        $questionnaireSidebarText = $this->extractSidebarNavigationText($questionnaireHtml);
        $this->assertStringContainsString('Satisfaction Questionnaires', $questionnaireSidebarText);
        $this->assertStringNotContainsString('Kuesioner Kepuasan', $questionnaireSidebarText);
        $this->assertStringNotContainsStringIgnoringCase('peran', $questionnaireSidebarText);
        $questionnaireResponse
            ->assertDontSee('Kuesioner Kepuasan')
            ->assertDontSee('Peran');

        $representativeTitles = [
            FacilityServiceResource::getUrl('index') => 'Facilities & Services',
            FacilityServiceResource::getUrl('create') => 'Create Facility & Service',
            RoomFacilityResource::getUrl('index') => 'Inpatient Rooms',
            RoomFacilityResource::getUrl('create') => 'Create Inpatient Room',
            RoleResource::getUrl('create') => 'Create Role',
        ];

        foreach ($representativeTitles as $url => $expectedPageTitle) {
            $response = $this->get($url)->assertOk();
            $this->assertSame(
                $this->expectedDocumentTitle($expectedPageTitle),
                $this->extractPageTitle($response->getContent()),
                "Representative <title> mismatch at [{$url}]"
            );
        }
    }

    public function test_indonesian_widgets_form_labels_and_action_buttons_remain_intact(): void
    {
        $this->actingAs($this->superAdmin);

        Livewire::test(FeedbackSectionWidget::class)
            ->assertSee('Kritik')
            ->assertSee('Saran Pasien')
            ->assertSee('Belum ada kritik dan saran masuk.');

        Livewire::test(QuestionnaireSectionWidget::class)
            ->assertSee('Kuesioner Kepuasan Pasien');

        $article = Article::create([
            'title' => ['id' => 'Artikel Aksi', 'en' => 'Action Article'],
            'slug' => ['id' => 'artikel-aksi', 'en' => 'action-article'],
            'content' => ['id' => 'Isi artikel.', 'en' => 'Article content.'],
        ]);

        $this->get(ArticleResource::getUrl('create'))
            ->assertOk()
            ->assertSee('Judul Artikel (Indonesia)')
            ->assertSee('Buat')
            ->assertSee('Batal');

        $this->get(ArticleResource::getUrl('edit', ['record' => $article]))
            ->assertOk()
            ->assertSee('Judul Artikel (Indonesia)')
            ->assertSee('Simpan')
            ->assertSee('Batal');

        $this->get(RoleResource::getUrl('create'))
            ->assertOk()
            ->assertSee('Nama')
            ->assertSee('Pilih Semua')
            ->assertSee('Lihat semua')
            ->assertSee('Buat')
            ->assertSee('Batal')
            ->assertDontSee('Buat Peran');
    }

    public function test_route_accessibility_and_existing_slugs_across_panel_roles(): void
    {
        $this->assertStringEndsWith('/admin/facility-services', FacilityServiceResource::getUrl('index'));
        $this->assertStringEndsWith('/admin/room-facilities', RoomFacilityResource::getUrl('index'));
        $this->assertStringEndsWith('/admin/feedbacks/feedback', FeedbackResource::getUrl('index'));
        $this->assertStringEndsWith('/admin/shield/roles', RoleResource::getUrl('index'));

        session()->flush();
        $this->actingAs($this->marketing);
        foreach ([
            '/admin',
            '/admin/articles',
            '/admin/articles/create',
            '/admin/banner-promotions',
            '/admin/doctors',
            '/admin/facility-services',
            '/admin/feedbacks/feedback',
            '/admin/room-facilities',
            '/admin/news',
            '/admin/online-libraries',
            '/admin/promotions',
            '/admin/questionnaires',
        ] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/shield/roles')->assertForbidden();

        session()->flush();
        $this->actingAs($this->staffIt);
        foreach ([
            '/admin',
            '/admin/articles',
            '/admin/banner-promotions',
            '/admin/doctors',
            '/admin/facility-services',
            '/admin/feedbacks/feedback',
            '/admin/room-facilities',
            '/admin/news',
            '/admin/online-libraries',
            '/admin/promotions',
            '/admin/questionnaires',
        ] as $url) {
            $this->get($url)->assertOk();
        }
        $this->get('/admin/articles/create')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/shield/roles')->assertForbidden();
    }

    private function extractPageHeading(string $html): string
    {
        if (! preg_match('/<h1[^>]*class="[^"]*fi-header-heading[^"]*"[^>]*>(.*?)<\/h1>/s', $html, $matches)) {
            $this->fail('Could not find <h1 class="fi-header-heading"> in rendered HTML.');
        }

        $decoded = html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/', ' ', $decoded));
    }

    private function extractPageTitle(string $html): string
    {
        if (! preg_match('/<title[^>]*>(.*?)<\/title>/si', $html, $matches)) {
            $this->fail('Could not find <title> in rendered HTML.');
        }

        $decoded = html_entity_decode(strip_tags($matches[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/', ' ', $decoded));
    }

    private function normalizedBrandName(): string
    {
        $brandName = strip_tags((string) Filament::getPanel('admin')->getBrandName());

        return trim((string) preg_replace('/\s+/', ' ', $brandName));
    }

    private function expectedDocumentTitle(string $pageTitle): string
    {
        $brandName = $this->normalizedBrandName();

        return filled($brandName) ? "{$pageTitle} - {$brandName}" : $pageTitle;
    }

    private function extractDocumentPageTitle(string $html): string
    {
        $fullTitle = $this->extractPageTitle($html);
        $brandName = $this->normalizedBrandName();

        if (filled($brandName) && str_ends_with($fullTitle, " - {$brandName}")) {
            return substr($fullTitle, 0, -strlen(" - {$brandName}"));
        }

        return $fullTitle;
    }

    /**
     * @return list<string>
     */
    private function extractSectionHeadings(string $html): array
    {
        preg_match_all(
            '/<(?:h[1-6]|p)[^>]*class="[^"]*fi-section-header-heading[^"]*"[^>]*>(.*?)<\/(?:h[1-6]|p)>/s',
            $html,
            $matches
        );

        return array_values(array_map(
            fn (string $heading): string => trim((string) preg_replace(
                '/\s+/',
                ' ',
                html_entity_decode(strip_tags($heading), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            )),
            $matches[1]
        ));
    }

    private function extractSidebarNavigationText(string $html): string
    {
        if (! preg_match('/<nav[^>]*class="[^"]*fi-sidebar-nav[^"]*"[^>]*>(.*?)<\/nav>/s', $html, $matches)) {
            $this->fail('Could not find <nav class="fi-sidebar-nav"> in rendered HTML.');
        }

        $withoutScripts = (string) preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $matches[1]);
        $decoded = html_entity_decode(strip_tags($withoutScripts), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/', ' ', $decoded));
    }
}
