<?php

namespace Tests\Feature;

use App\Enums\AdminNavigationGroup;
use App\Filament\Resources\Articles\ArticleResource;
use App\Filament\Resources\BannerPromotions\BannerPromotionResource;
use App\Filament\Resources\Doctors\DoctorResource;
use App\Filament\Resources\DynamicForms\DynamicFormResource;
use App\Filament\Resources\DynamicFormSubmissions\DynamicFormSubmissionResource;
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
use App\Http\Middleware\SetAdminPanelLocale;
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
use DOMDocument;
use DOMElement;
use DOMXPath;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Js;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Tests\Concerns\SeedsTestRolesAndUsers;
use Tests\TestCase;

class CmsNavigationLocalizationTest extends TestCase
{
    use RefreshDatabase;
    use SeedsTestRolesAndUsers;

    private User $superAdmin;

    private User $marketing;

    private User $staffIt;

    protected function setUp(): void
    {
        parent::setUp();

        config(['filesystems.media_disk' => 'media-test']);
        Storage::fake('media-test');

        $this->seedDefaultTestUsersAndRoles();
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
            'ViewAny:DynamicForm',
            'ViewAny:DynamicFormSubmission',
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

    public function test_resource_navigation_plural_and_singular_labels_match_standard_for_both_locales(): void
    {
        $this->actingAs($this->superAdmin);

        $localeStandards = [
            'id' => [
                'dashboard' => 'Dasbor',
                'resources' => [
                    ArticleResource::class => [
                        'nav' => 'Artikel',
                        'plural' => 'Artikel',
                        'singular' => 'Artikel',
                        'group_enum' => AdminNavigationGroup::MarketingEditorial,
                        'group_label' => 'Pemasaran & Editorial',
                        'sort' => 1,
                    ],
                    BannerPromotionResource::class => [
                        'nav' => 'Banner Promosi',
                        'plural' => 'Banner Promosi',
                        'singular' => 'Banner Promosi',
                        'group_enum' => AdminNavigationGroup::MarketingEditorial,
                        'group_label' => 'Pemasaran & Editorial',
                        'sort' => 2,
                    ],
                    NewsResource::class => [
                        'nav' => 'Berita',
                        'plural' => 'Berita',
                        'singular' => 'Berita',
                        'group_enum' => AdminNavigationGroup::MarketingEditorial,
                        'group_label' => 'Pemasaran & Editorial',
                        'sort' => 3,
                    ],
                    PromotionResource::class => [
                        'nav' => 'Promosi',
                        'plural' => 'Promosi',
                        'singular' => 'Promosi',
                        'group_enum' => AdminNavigationGroup::MarketingEditorial,
                        'group_label' => 'Pemasaran & Editorial',
                        'sort' => 4,
                    ],
                    FacilityServiceResource::class => [
                        'nav' => 'Fasilitas & Layanan',
                        'plural' => 'Fasilitas & Layanan',
                        'singular' => 'Fasilitas & Layanan',
                        'group_enum' => AdminNavigationGroup::ServicesRooms,
                        'group_label' => 'Layanan & Kamar',
                        'sort' => 1,
                    ],
                    RoomFacilityResource::class => [
                        'nav' => 'Kamar Rawat Inap',
                        'plural' => 'Kamar Rawat Inap',
                        'singular' => 'Kamar Rawat Inap',
                        'group_enum' => AdminNavigationGroup::ServicesRooms,
                        'group_label' => 'Layanan & Kamar',
                        'sort' => 2,
                    ],
                    DoctorResource::class => [
                        'nav' => 'Profil Dokter',
                        'plural' => 'Profil Dokter',
                        'singular' => 'Profil Dokter',
                        'group_enum' => AdminNavigationGroup::DoctorsLibrary,
                        'group_label' => 'Dokter & Perpustakaan',
                        'sort' => 1,
                    ],
                    OnlineLibraryResource::class => [
                        'nav' => 'Perpustakaan Online',
                        'plural' => 'Perpustakaan Online',
                        'singular' => 'Perpustakaan Online',
                        'group_enum' => AdminNavigationGroup::DoctorsLibrary,
                        'group_label' => 'Dokter & Perpustakaan',
                        'sort' => 2,
                    ],
                    FeedbackResource::class => [
                        'nav' => 'Kritik & Saran',
                        'plural' => 'Kritik & Saran',
                        'singular' => 'Kritik & Saran',
                        'group_enum' => AdminNavigationGroup::PatientFeedback,
                        'group_label' => 'Masukan Pasien',
                        'sort' => 1,
                    ],
                    QuestionnaireResource::class => [
                        'nav' => 'Kuesioner Kepuasan',
                        'plural' => 'Kuesioner Kepuasan',
                        'singular' => 'Kuesioner Kepuasan',
                        'group_enum' => AdminNavigationGroup::PatientFeedback,
                        'group_label' => 'Masukan Pasien',
                        'sort' => 2,
                    ],
                    DynamicFormResource::class => [
                        'nav' => 'Formulir Kuesioner Kustom',
                        'plural' => 'Formulir Kuesioner Kustom',
                        'singular' => 'Formulir Kuesioner Kustom',
                        'group_enum' => AdminNavigationGroup::PatientFeedback,
                        'group_label' => 'Masukan Pasien',
                        'sort' => 3,
                    ],
                    DynamicFormSubmissionResource::class => [
                        'nav' => 'Respons Formulir Kustom',
                        'plural' => 'Respons Formulir Kustom',
                        'singular' => 'Respons Formulir Kustom',
                        'group_enum' => AdminNavigationGroup::PatientFeedback,
                        'group_label' => 'Masukan Pasien',
                        'sort' => 4,
                    ],
                    UserResource::class => [
                        'nav' => 'Manajemen Pengguna',
                        'plural' => 'Pengguna',
                        'singular' => 'Pengguna',
                        'group_enum' => AdminNavigationGroup::AccessControl,
                        'group_label' => 'Kontrol Akses',
                        'sort' => 1,
                    ],
                    RoleResource::class => [
                        'nav' => 'Peran',
                        'plural' => 'Peran',
                        'singular' => 'Peran',
                        'group_enum' => AdminNavigationGroup::AccessControl,
                        'group_label' => 'Kontrol Akses',
                        'sort' => 2,
                    ],
                ],
            ],
            'en' => [
                'dashboard' => 'Dashboard',
                'resources' => [
                    ArticleResource::class => [
                        'nav' => 'Articles',
                        'plural' => 'Articles',
                        'singular' => 'Article',
                        'group_enum' => AdminNavigationGroup::MarketingEditorial,
                        'group_label' => 'Marketing & Editorial',
                        'sort' => 1,
                    ],
                    BannerPromotionResource::class => [
                        'nav' => 'Banner Promotions',
                        'plural' => 'Banner Promotions',
                        'singular' => 'Banner Promotion',
                        'group_enum' => AdminNavigationGroup::MarketingEditorial,
                        'group_label' => 'Marketing & Editorial',
                        'sort' => 2,
                    ],
                    NewsResource::class => [
                        'nav' => 'News',
                        'plural' => 'News',
                        'singular' => 'News',
                        'group_enum' => AdminNavigationGroup::MarketingEditorial,
                        'group_label' => 'Marketing & Editorial',
                        'sort' => 3,
                    ],
                    PromotionResource::class => [
                        'nav' => 'Promotions',
                        'plural' => 'Promotions',
                        'singular' => 'Promotion',
                        'group_enum' => AdminNavigationGroup::MarketingEditorial,
                        'group_label' => 'Marketing & Editorial',
                        'sort' => 4,
                    ],
                    FacilityServiceResource::class => [
                        'nav' => 'Facilities & Services',
                        'plural' => 'Facilities & Services',
                        'singular' => 'Facility & Service',
                        'group_enum' => AdminNavigationGroup::ServicesRooms,
                        'group_label' => 'Services & Rooms',
                        'sort' => 1,
                    ],
                    RoomFacilityResource::class => [
                        'nav' => 'Inpatient Rooms',
                        'plural' => 'Inpatient Rooms',
                        'singular' => 'Inpatient Room',
                        'group_enum' => AdminNavigationGroup::ServicesRooms,
                        'group_label' => 'Services & Rooms',
                        'sort' => 2,
                    ],
                    DoctorResource::class => [
                        'nav' => 'Doctor Profiles',
                        'plural' => 'Doctor Profiles',
                        'singular' => 'Doctor Profile',
                        'group_enum' => AdminNavigationGroup::DoctorsLibrary,
                        'group_label' => 'Doctors & Library',
                        'sort' => 1,
                    ],
                    OnlineLibraryResource::class => [
                        'nav' => 'Online Library',
                        'plural' => 'Online Library',
                        'singular' => 'Online Library',
                        'group_enum' => AdminNavigationGroup::DoctorsLibrary,
                        'group_label' => 'Doctors & Library',
                        'sort' => 2,
                    ],
                    FeedbackResource::class => [
                        'nav' => 'Feedback & Suggestions',
                        'plural' => 'Feedback & Suggestions',
                        'singular' => 'Feedback',
                        'group_enum' => AdminNavigationGroup::PatientFeedback,
                        'group_label' => 'Patient Feedback',
                        'sort' => 1,
                    ],
                    QuestionnaireResource::class => [
                        'nav' => 'Satisfaction Questionnaires',
                        'plural' => 'Satisfaction Questionnaires',
                        'singular' => 'Satisfaction Questionnaire',
                        'group_enum' => AdminNavigationGroup::PatientFeedback,
                        'group_label' => 'Patient Feedback',
                        'sort' => 2,
                    ],
                    DynamicFormResource::class => [
                        'nav' => 'Custom Questionnaire Form',
                        'plural' => 'Custom Questionnaire Forms',
                        'singular' => 'Custom Questionnaire Form',
                        'group_enum' => AdminNavigationGroup::PatientFeedback,
                        'group_label' => 'Patient Feedback',
                        'sort' => 3,
                    ],
                    DynamicFormSubmissionResource::class => [
                        'nav' => 'Custom Questionnaire Responses',
                        'plural' => 'Custom Questionnaire Responses',
                        'singular' => 'Custom Questionnaire Response',
                        'group_enum' => AdminNavigationGroup::PatientFeedback,
                        'group_label' => 'Patient Feedback',
                        'sort' => 4,
                    ],
                    UserResource::class => [
                        'nav' => 'User Management',
                        'plural' => 'Users',
                        'singular' => 'User',
                        'group_enum' => AdminNavigationGroup::AccessControl,
                        'group_label' => 'Access Control',
                        'sort' => 1,
                    ],
                    RoleResource::class => [
                        'nav' => 'Roles',
                        'plural' => 'Roles',
                        'singular' => 'Role',
                        'group_enum' => AdminNavigationGroup::AccessControl,
                        'group_label' => 'Access Control',
                        'sort' => 2,
                    ],
                ],
            ],
        ];

        foreach ($localeStandards as $locale => $standard) {
            App::setLocale($locale);

            $this->assertSame($standard['dashboard'], Dashboard::getNavigationLabel(), "[{$locale}] Dashboard nav mismatch");
            $this->assertSame($standard['dashboard'], (new Dashboard)->getTitle(), "[{$locale}] Dashboard title mismatch");
            $this->assertNull(Dashboard::getNavigationGroup());

            foreach ($standard['resources'] as $resource => $labels) {
                $this->assertSame($labels['nav'], $resource::getNavigationLabel(), "[{$locale}] {$resource} navigationLabel mismatch");
                $this->assertSame($labels['plural'], $resource::getTitleCasePluralModelLabel(), "[{$locale}] {$resource} pluralModelLabel mismatch");
                $this->assertSame($labels['singular'], $resource::getTitleCaseModelLabel(), "[{$locale}] {$resource} modelLabel mismatch");

                $group = $resource::getNavigationGroup();
                $this->assertSame($labels['group_enum'], $group, "[{$locale}] {$resource} navigationGroup enum mismatch");
                $this->assertSame($labels['group_label'], $group->getLabel(), "[{$locale}] {$resource} navigationGroup label mismatch");
                $this->assertSame($labels['sort'], $resource::getNavigationSort(), "[{$locale}] {$resource} navigationSort mismatch");
            }
        }

        App::setLocale('id');
    }

    public function test_super_admin_sidebar_navigation_groups_labels_and_order_in_both_locales(): void
    {
        $this->actingAs($this->superAdmin);

        $localeCases = [
            'id' => [
                'dashboard' => 'Dasbor',
                'groups' => [
                    ['label' => null, 'items' => ['Dasbor']],
                    ['label' => 'Pemasaran & Editorial', 'items' => ['Artikel', 'Banner Promosi', 'Berita', 'Promosi']],
                    ['label' => 'Layanan & Kamar', 'items' => ['Fasilitas & Layanan', 'Kamar Rawat Inap']],
                    ['label' => 'Dokter & Perpustakaan', 'items' => ['Profil Dokter', 'Perpustakaan Online']],
                    ['label' => 'Masukan Pasien', 'items' => ['Kritik & Saran', 'Kuesioner Kepuasan', 'Formulir Kuesioner Kustom', 'Respons Formulir Kustom']],
                    ['label' => 'Kontrol Akses', 'items' => ['Manajemen Pengguna', 'Peran']],
                ],
                'encodedGroups' => [
                    'Pemasaran &amp; Editorial',
                    'Layanan &amp; Kamar',
                    'Dokter &amp; Perpustakaan',
                    'Masukan Pasien',
                    'Kontrol Akses',
                ],
                'activeRoutes' => [
                    '/admin/articles' => ['Pemasaran & Editorial', 'Artikel'],
                    '/admin/facility-services' => ['Layanan & Kamar', 'Fasilitas & Layanan'],
                    '/admin/doctors' => ['Dokter & Perpustakaan', 'Profil Dokter'],
                    '/admin/feedbacks/feedback' => ['Masukan Pasien', 'Kritik & Saran'],
                    '/admin/users' => ['Kontrol Akses', 'Manajemen Pengguna'],
                    '/admin/shield/roles' => ['Kontrol Akses', 'Peran'],
                ],
                'forbiddenSidebar' => [
                    'Dashboard',
                    'Marketing & Editorial',
                    'Access Control',
                    'Facility Services',
                    'Room Facilities',
                    'Administration',
                    'Administrasi',
                    'Filament Shield',
                    'Konten Halaman Utama',
                ],
            ],
            'en' => [
                'dashboard' => 'Dashboard',
                'groups' => [
                    ['label' => null, 'items' => ['Dashboard']],
                    ['label' => 'Marketing & Editorial', 'items' => ['Articles', 'Banner Promotions', 'News', 'Promotions']],
                    ['label' => 'Services & Rooms', 'items' => ['Facilities & Services', 'Inpatient Rooms']],
                    ['label' => 'Doctors & Library', 'items' => ['Doctor Profiles', 'Online Library']],
                    ['label' => 'Patient Feedback', 'items' => ['Feedback & Suggestions', 'Satisfaction Questionnaires', 'Custom Questionnaire Form', 'Custom Questionnaire Responses']],
                    ['label' => 'Access Control', 'items' => ['User Management', 'Roles']],
                ],
                'encodedGroups' => [
                    'Marketing &amp; Editorial',
                    'Services &amp; Rooms',
                    'Doctors &amp; Library',
                    'Patient Feedback',
                    'Access Control',
                ],
                'activeRoutes' => [
                    '/admin/articles' => ['Marketing & Editorial', 'Articles'],
                    '/admin/facility-services' => ['Services & Rooms', 'Facilities & Services'],
                    '/admin/doctors' => ['Doctors & Library', 'Doctor Profiles'],
                    '/admin/feedbacks/feedback' => ['Patient Feedback', 'Feedback & Suggestions'],
                    '/admin/users' => ['Access Control', 'User Management'],
                    '/admin/shield/roles' => ['Access Control', 'Roles'],
                ],
                'forbiddenSidebar' => [
                    'Dasbor',
                    'Pemasaran & Editorial',
                    'Kontrol Akses',
                    'Profil Dokter',
                    'Facility Services',
                    'Room Facilities',
                    'Facilities And Services',
                    'Perpustakaan Online',
                    'Kuesioner Kepuasan',
                    'Kritik & Saran',
                    'Manajemen User',
                    'Manajemen Pengguna',
                    'Administration',
                    'Administrasi',
                    'Filament Shield',
                    'Konten Halaman Utama',
                    'Peran',
                ],
            ],
        ];

        foreach ($localeCases as $locale => $case) {
            App::setLocale($locale);
            app()->forgetScopedInstances();

            $panel = Filament::getPanel('admin');
            $groups = array_values($panel->getNavigation());

            $this->assertCount(6, $groups, "[{$locale}] Navigation group count mismatch");

            foreach ($case['groups'] as $index => $expectedGroup) {
                /** @var NavigationGroup $group */
                $group = $groups[$index];
                $this->assertSame($expectedGroup['label'], $group->getLabel(), "[{$locale}] Group label mismatch at index {$index}");

                if ($expectedGroup['label'] !== null) {
                    $this->assertTrue($group->isCollapsible(), "[{$locale}] Group [{$expectedGroup['label']}] should be collapsible.");
                    $this->assertFalse($group->isCollapsed(), "[{$locale}] Group [{$expectedGroup['label']}] should be expanded by default.");
                    $this->assertNull($group->getIcon(), "[{$locale}] Group [{$expectedGroup['label']}] should not define a group icon.");
                }

                $items = collect($group->getItems())->values();
                $this->assertSame(
                    $expectedGroup['items'],
                    $items->map(fn (NavigationItem $item): string => $item->getLabel())->all(),
                    "[{$locale}] Navigation items mismatch for group [{$expectedGroup['label']}]"
                );

                foreach ($items as $item) {
                    $this->assertNotNull($item->getIcon(), "[{$locale}] Submenu item [{$item->getLabel()}] should retain its icon.");
                }
            }

            $response = $this->withSession([SetAdminPanelLocale::SESSION_KEY => $locale])
                ->get('/admin')
                ->assertOk();
            $html = $response->getContent();
            $this->assertSame($case['dashboard'], $this->extractPageHeading($html));
            $this->assertSame($this->expectedDocumentTitle($case['dashboard']), $this->extractPageTitle($html));

            $sidebarHtml = $this->extractSidebarNavigationHtml($html);
            foreach ($case['encodedGroups'] as $encodedGroupLabel) {
                $this->assertSame(
                    1,
                    preg_match(
                        '/<li\b[^>]*data-group-label="'.preg_quote($encodedGroupLabel, '/').'"[^>]*\sclass="([^"]*)"/s',
                        $sidebarHtml,
                        $groupAttrMatches
                    ),
                    "[{$locale}] Expected collapsible sidebar group [{$encodedGroupLabel}] in rendered HTML."
                );
                $groupClasses = preg_split('/\s+/', trim($groupAttrMatches[1])) ?: [];
                $this->assertContains('fi-sidebar-group', $groupClasses);
                $this->assertContains('fi-collapsible', $groupClasses);
                $this->assertNotContains('fi-collapsed', $groupClasses);
            }

            $this->assertStringContainsString('JSON.stringify([])', $sidebarHtml);
            $this->assertStringNotContainsString('&amp;amp;', $sidebarHtml);

            $expectedItemFlat = collect($case['groups'])->flatMap(fn (array $g): array => $g['items'])->values()->all();
            $renderedItemLabels = $this->extractSidebarItemLabels($sidebarHtml);
            $this->assertSame($expectedItemFlat, $renderedItemLabels);

            $this->assertSame(
                count($renderedItemLabels),
                preg_match_all('/<svg[^>]*class="[^"]*fi-sidebar-item-icon[^"]*"/s', $sidebarHtml),
                "[{$locale}] Every sidebar navigation item must render its submenu icon SVG."
            );

            $this->assertSidebarDomStructure($html, $case['groups'], null, $case['dashboard']);

            foreach ($case['activeRoutes'] as $route => [$activeGroup, $activeItem]) {
                app()->forgetScopedInstances();
                $routeHtml = $this->withSession([SetAdminPanelLocale::SESSION_KEY => $locale])
                    ->get($route)
                    ->assertOk()
                    ->getContent();
                $this->assertSidebarDomStructure($routeHtml, $case['groups'], $activeGroup, $activeItem);
            }

            $sidebarText = $this->extractSidebarNavigationText($html);
            foreach ($case['forbiddenSidebar'] as $forbiddenSidebarLabel) {
                $this->assertStringNotContainsString($forbiddenSidebarLabel, $sidebarText, "[{$locale}] Unexpected [{$forbiddenSidebarLabel}] in sidebar.");
            }
        }

        App::setLocale('id');
    }

    public function test_role_based_sidebar_navigation_visibility_hides_inaccessible_and_empty_groups(): void
    {
        $panelOnlyRole = Role::findOrCreate('panel_user', 'web');
        $panelOnlyUser = User::factory()->create(['must_change_password' => false]);
        $panelOnlyUser->assignRole($panelOnlyRole);

        $expectedStaffGroupsId = [
            ['label' => null, 'items' => ['Dasbor']],
            ['label' => 'Pemasaran & Editorial', 'items' => ['Artikel', 'Banner Promosi', 'Berita', 'Promosi']],
            ['label' => 'Layanan & Kamar', 'items' => ['Fasilitas & Layanan', 'Kamar Rawat Inap']],
            ['label' => 'Dokter & Perpustakaan', 'items' => ['Profil Dokter', 'Perpustakaan Online']],
            ['label' => 'Masukan Pasien', 'items' => ['Kritik & Saran', 'Kuesioner Kepuasan', 'Formulir Kuesioner Kustom', 'Respons Formulir Kustom']],
        ];

        $expectedStaffGroupsEn = [
            ['label' => null, 'items' => ['Dashboard']],
            ['label' => 'Marketing & Editorial', 'items' => ['Articles', 'Banner Promotions', 'News', 'Promotions']],
            ['label' => 'Services & Rooms', 'items' => ['Facilities & Services', 'Inpatient Rooms']],
            ['label' => 'Doctors & Library', 'items' => ['Doctor Profiles', 'Online Library']],
            ['label' => 'Patient Feedback', 'items' => ['Feedback & Suggestions', 'Satisfaction Questionnaires', 'Custom Questionnaire Form', 'Custom Questionnaire Responses']],
        ];

        foreach ([$this->marketing, $this->staffIt] as $staffUser) {
            $this->switchAuthenticatedUser($staffUser);

            // Default Indonesian locale
            $panel = Filament::getPanel('admin');
            $groups = array_values($panel->getNavigation());
            $groupLabels = collect($groups)->map(fn (NavigationGroup $group): ?string => $group->getLabel())->all();

            $this->assertSame([
                null,
                'Pemasaran & Editorial',
                'Layanan & Kamar',
                'Dokter & Perpustakaan',
                'Masukan Pasien',
            ], $groupLabels);

            foreach ($groups as $group) {
                $this->assertNotEmpty($group->getItems(), "Group [{$group->getLabel()}] must not be empty.");
            }

            $response = $this->get('/admin')->assertOk();
            $html = $response->getContent();
            $this->assertSame('Dasbor', $this->extractPageHeading($html));
            $this->assertSame($this->expectedDocumentTitle('Dasbor'), $this->extractPageTitle($html));
            $this->assertSidebarDomStructure($html, $expectedStaffGroupsId, null, 'Dasbor');

            $sidebarText = $this->extractSidebarNavigationText($html);
            $this->assertStringNotContainsString('Kontrol Akses', $sidebarText);
            $this->assertStringNotContainsString('Manajemen Pengguna', $sidebarText);
            $this->assertStringNotContainsString('Peran', $sidebarText);
            $response->assertDontSee('Kontrol Akses');
            $response->assertDontSee('Manajemen Pengguna');

            // English locale via session
            app()->forgetScopedInstances();
            $enResponse = $this->withSession([SetAdminPanelLocale::SESSION_KEY => 'en'])
                ->get('/admin')
                ->assertOk();
            $enHtml = $enResponse->getContent();
            $this->assertSame('Dashboard', $this->extractPageHeading($enHtml));
            $this->assertSidebarDomStructure($enHtml, $expectedStaffGroupsEn, null, 'Dashboard');
            $enResponse->assertDontSee('Access Control');
            $enResponse->assertDontSee('User Management');
        }

        // panel_user hanya melihat Dasbor dan Masukan Pasien (via PatientSubmissionPolicy).
        $this->switchAuthenticatedUser($panelOnlyUser);

        $panel = Filament::getPanel('admin');
        $panelUserGroups = array_values($panel->getNavigation());
        $panelUserGroupLabels = collect($panelUserGroups)->map(fn (NavigationGroup $group): ?string => $group->getLabel())->all();

        $expectedPanelUserGroupsId = [
            ['label' => null, 'items' => ['Dasbor']],
            ['label' => 'Masukan Pasien', 'items' => ['Kritik & Saran', 'Kuesioner Kepuasan']],
        ];

        $this->assertSame([null, 'Masukan Pasien'], $panelUserGroupLabels);
        $this->assertSame(
            ['Dasbor'],
            collect($panelUserGroups[0]->getItems())->map(fn (NavigationItem $item): string => $item->getLabel())->values()->all()
        );
        $this->assertSame(
            ['Kritik & Saran', 'Kuesioner Kepuasan'],
            collect($panelUserGroups[1]->getItems())->map(fn (NavigationItem $item): string => $item->getLabel())->values()->all()
        );

        $panelUserResponse = $this->get('/admin')->assertOk();
        $panelUserHtml = $panelUserResponse->getContent();
        $this->assertSame('Dasbor', $this->extractPageHeading($panelUserHtml));
        $this->assertSidebarDomStructure($panelUserHtml, $expectedPanelUserGroupsId, null, 'Dasbor');

        $panelUserSidebarHtml = $this->extractSidebarNavigationHtml($panelUserHtml);
        $this->assertSame(
            ['Dasbor', 'Kritik & Saran', 'Kuesioner Kepuasan'],
            $this->extractSidebarItemLabels($panelUserSidebarHtml)
        );
        $this->assertSame(1, preg_match_all('/<li\b[^>]*\bdata-group-label="([^"]+)"/s', $panelUserSidebarHtml, $panelUserRenderedGroups));
        $this->assertSame(['Masukan Pasien'], $panelUserRenderedGroups[1]);

        $panelUserSidebarText = $this->extractSidebarNavigationText($panelUserHtml);
        foreach ([
            'Pemasaran & Editorial',
            'Layanan & Kamar',
            'Dokter & Perpustakaan',
            'Kontrol Akses',
            'Artikel',
            'Banner Promosi',
            'Berita',
            'Promosi',
            'Fasilitas & Layanan',
            'Kamar Rawat Inap',
            'Profil Dokter',
            'Perpustakaan Online',
            'Manajemen Pengguna',
            'Peran',
        ] as $hiddenForPanelUser) {
            $this->assertStringNotContainsString($hiddenForPanelUser, $panelUserSidebarText);
        }

        app()->forgetScopedInstances();
        $panelUserFeedbackHtml = $this->get('/admin/feedbacks/feedback')->assertOk()->getContent();
        $this->assertSidebarDomStructure($panelUserFeedbackHtml, $expectedPanelUserGroupsId, 'Masukan Pasien', 'Kritik & Saran');

        app()->forgetScopedInstances();
        $panelUserQuestionnaireHtml = $this->get('/admin/questionnaires')->assertOk()->getContent();
        $this->assertSidebarDomStructure($panelUserQuestionnaireHtml, $expectedPanelUserGroupsId, 'Masukan Pasien', 'Kuesioner Kepuasan');

        $this->get('/admin/articles')->assertForbidden();
        $this->get('/admin/facility-services')->assertForbidden();
        $this->get('/admin/doctors')->assertForbidden();
        $this->get('/admin/users')->assertForbidden();
        $this->get('/admin/shield/roles')->assertForbidden();

        // Role dengan izin parsial hanya menampilkan grup yang memiliki minimal satu item yang dapat diakses.
        $partialRole = Role::findOrCreate('article_viewer', 'web');
        $partialRole->syncPermissions(['ViewAny:Article', 'View:Article']);
        $partialUser = User::factory()->create(['must_change_password' => false]);
        $partialUser->assignRole($partialRole);
        $this->switchAuthenticatedUser($partialUser);

        $expectedPartialGroupsId = [
            ['label' => null, 'items' => ['Dasbor']],
            ['label' => 'Pemasaran & Editorial', 'items' => ['Artikel']],
            ['label' => 'Masukan Pasien', 'items' => ['Kritik & Saran', 'Kuesioner Kepuasan']],
        ];

        $partialNavGroups = array_values(Filament::getPanel('admin')->getNavigation());
        $this->assertSame(
            [null, 'Pemasaran & Editorial', 'Masukan Pasien'],
            collect($partialNavGroups)->map(fn (NavigationGroup $group): ?string => $group->getLabel())->all()
        );
        $partialDashboardHtml = $this->get('/admin')->assertOk()->getContent();
        $this->assertSidebarDomStructure($partialDashboardHtml, $expectedPartialGroupsId, null, 'Dasbor');

        // Pemberian permission User/Role langsung ke non-super_admin tetap tidak membuka grup Kontrol Akses.
        $panelOnlyUser->givePermissionTo(['ViewAny:User', 'ViewAny:Role']);
        $this->switchAuthenticatedUser($panelOnlyUser);
        $groupsAfterDirectPerm = array_values(Filament::getPanel('admin')->getNavigation());
        $this->assertSame(
            [null, 'Masukan Pasien'],
            collect($groupsAfterDirectPerm)->map(fn (NavigationGroup $group): ?string => $group->getLabel())->all()
        );

        // User tanpa role tidak memiliki grup navigasi yang tersisa maupun grup kosong.
        $rolelessUser = User::factory()->create(['must_change_password' => false]);
        $this->switchAuthenticatedUser($rolelessUser);
        $rolelessGroups = array_values(Filament::getPanel('admin')->getNavigation());
        $this->assertSame(
            [null],
            collect($rolelessGroups)->map(fn (NavigationGroup $group): ?string => $group->getLabel())->all()
        );
        $this->get('/admin')->assertForbidden();
    }

    public function test_list_create_edit_and_view_page_headings_switch_cleanly_between_id_and_en(): void
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

        $expectationsByLocale = [
            'id' => [
                ArticleResource::getUrl('index') => 'Artikel',
                BannerPromotionResource::getUrl('index') => 'Banner Promosi',
                DoctorResource::getUrl('index') => 'Profil Dokter',
                FacilityServiceResource::getUrl('index') => 'Fasilitas & Layanan',
                FeedbackResource::getUrl('index') => 'Kritik & Saran',
                RoomFacilityResource::getUrl('index') => 'Kamar Rawat Inap',
                NewsResource::getUrl('index') => 'Berita',
                OnlineLibraryResource::getUrl('index') => 'Perpustakaan Online',
                PromotionResource::getUrl('index') => 'Promosi',
                QuestionnaireResource::getUrl('index') => 'Kuesioner Kepuasan',
                UserResource::getUrl('index') => 'Pengguna',
                RoleResource::getUrl('index') => 'Peran',
                ArticleResource::getUrl('create') => 'Buat Artikel',
                BannerPromotionResource::getUrl('create') => 'Buat Banner Promosi',
                FacilityServiceResource::getUrl('create') => 'Buat Fasilitas & Layanan',
                RoomFacilityResource::getUrl('create') => 'Buat Kamar Rawat Inap',
                NewsResource::getUrl('create') => 'Buat Berita',
                OnlineLibraryResource::getUrl('create') => 'Buat Perpustakaan Online',
                PromotionResource::getUrl('create') => 'Buat Promosi',
                UserResource::getUrl('create') => 'Buat Pengguna',
                RoleResource::getUrl('create') => 'Buat Peran',
                ArticleResource::getUrl('edit', ['record' => $article]) => 'Ubah Artikel Uji',
                BannerPromotionResource::getUrl('edit', ['record' => $banner]) => 'Ubah Banner Uji',
                DoctorResource::getUrl('edit', ['record' => $doctor]) => 'Ubah dr. Test Navigation',
                FacilityServiceResource::getUrl('edit', ['record' => $facility]) => 'Ubah Layanan IGD',
                FacilityServiceResource::getUrl('edit', ['record' => $facilityWithAmpersand]) => 'Ubah Layanan Ibu & Anak',
                RoomFacilityResource::getUrl('edit', ['record' => $room]) => 'Ubah Ruang Anggrek',
                NewsResource::getUrl('edit', ['record' => $news]) => 'Ubah Berita Uji',
                OnlineLibraryResource::getUrl('edit', ['record' => $library]) => 'Ubah Dokumen Uji',
                PromotionResource::getUrl('edit', ['record' => $promotion]) => 'Ubah Promo Uji',
                UserResource::getUrl('edit', ['record' => $this->marketing]) => 'Ubah '.$this->marketing->name,
                RoleResource::getUrl('edit', ['record' => $role]) => 'Ubah marketing',
                RoleResource::getUrl('view', ['record' => $role]) => 'Lihat marketing',
                FeedbackResource::getUrl('view', ['record' => $feedback]) => 'Lihat FB-NAV-001',
                QuestionnaireResource::getUrl('view', ['record' => $questionnaire]) => 'Lihat Kuesioner Kepuasan',
            ],
            'en' => [
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
                ArticleResource::getUrl('create') => 'Create Article',
                BannerPromotionResource::getUrl('create') => 'Create Banner Promotion',
                FacilityServiceResource::getUrl('create') => 'Create Facility & Service',
                RoomFacilityResource::getUrl('create') => 'Create Inpatient Room',
                NewsResource::getUrl('create') => 'Create News',
                OnlineLibraryResource::getUrl('create') => 'Create Online Library',
                PromotionResource::getUrl('create') => 'Create Promotion',
                UserResource::getUrl('create') => 'Create User',
                RoleResource::getUrl('create') => 'Create Role',
                // Record title remains anchored to Indonesian primary title even in EN panel mode.
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
            ],
        ];

        foreach ($expectationsByLocale as $locale => $expectations) {
            foreach ($expectations as $url => $expectedHeading) {
                $response = $this->withSession([SetAdminPanelLocale::SESSION_KEY => $locale])
                    ->get($url)
                    ->assertOk();
                $html = $response->getContent();
                $heading = $this->extractPageHeading($html);
                $title = $this->extractPageTitle($html);
                $documentPageTitle = $this->extractDocumentPageTitle($html);
                $this->assertSame($expectedHeading, $heading, "[{$locale}] Heading mismatch at [{$url}]");
                $this->assertSame($this->expectedDocumentTitle($expectedHeading), $title, "[{$locale}] <title> mismatch at [{$url}]");
                $this->assertStringNotContainsString('And', $heading, "[{$locale}] Unexpected 'And' in [{$heading}]");
                $this->assertStringNotContainsString('And', $documentPageTitle, "[{$locale}] Unexpected 'And' in <title> [{$title}]");
            }
        }
    }

    public function test_facility_service_edit_page_preserves_ampersand_in_heading_and_title(): void
    {
        $this->actingAs($this->superAdmin);

        $facility = FacilityService::factory()->create([
            'name' => ['id' => 'Layanan Ibu & Anak', 'en' => 'Maternity & Child Service'],
            'slug' => ['id' => 'layanan-ibu-anak', 'en' => 'maternity-child-service'],
        ]);

        foreach ([
            'id' => 'Ubah Layanan Ibu & Anak',
            'en' => 'Edit Layanan Ibu & Anak',
        ] as $locale => $expectedHeading) {
            $response = $this->withSession([SetAdminPanelLocale::SESSION_KEY => $locale])
                ->get(FacilityServiceResource::getUrl('edit', ['record' => $facility]))
                ->assertOk();
            $html = $response->getContent();
            $heading = $this->extractPageHeading($html);
            $title = $this->extractPageTitle($html);
            $documentPageTitle = $this->extractDocumentPageTitle($html);

            $this->assertSame($expectedHeading, $heading);
            $this->assertSame($this->expectedDocumentTitle($expectedHeading), $title);
            $this->assertSame($expectedHeading, $documentPageTitle);
            $this->assertStringContainsString('&', $heading);
            $this->assertStringNotContainsString('And', $heading);
            $this->assertDoesNotMatchRegularExpression('/<h1[^>]*class="[^"]*fi-header-heading[^"]*"[^>]*>.*?&amp;amp;.*?<\/h1>/s', $html);
            $this->assertDoesNotMatchRegularExpression('/<title[^>]*>.*?&amp;amp;.*?<\/title>/si', $html);
            $response->assertDontSee('Layanan Ibu And Anak');
        }
    }

    public function test_shield_role_create_page_renders_localized_resource_permission_section_labels(): void
    {
        $this->actingAs($this->superAdmin);

        $this->assertStringEndsWith('/admin/shield/roles/create', RoleResource::getUrl('create'));

        $cases = [
            'id' => [
                'heading' => 'Buat Peran',
                'sections' => [
                    'Artikel',
                    'Banner Promosi',
                    'Berita',
                    'Fasilitas & Layanan',
                    'Formulir Kuesioner Kustom',
                    'Kamar Rawat Inap',
                    'Kritik & Saran',
                    'Kuesioner Kepuasan',
                    'Pengguna',
                    'Peran',
                    'Perpustakaan Online',
                    'Profil Dokter',
                    'Promosi',
                    'Respons Formulir Kustom',
                ],
            ],
            'en' => [
                'heading' => 'Create Role',
                'sections' => [
                    'Article',
                    'Banner Promotion',
                    'Custom Questionnaire Form',
                    'Custom Questionnaire Response',
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
                ],
            ],
        ];

        foreach ($cases as $locale => $case) {
            $response = $this->withSession([SetAdminPanelLocale::SESSION_KEY => $locale])
                ->get('/admin/shield/roles/create')
                ->assertOk();
            $html = $response->getContent();

            $this->assertSame($case['heading'], $this->extractPageHeading($html), "[{$locale}] Shield create heading mismatch");
            $this->assertSame($this->expectedDocumentTitle($case['heading']), $this->extractPageTitle($html));

            $sectionHeadings = $this->extractSectionHeadings($html);
            $this->assertCount(count($case['sections']), $sectionHeadings, "[{$locale}] Unexpected Shield section count.");

            foreach ($case['sections'] as $expectedSectionLabel) {
                $response->assertSee($expectedSectionLabel);
                $this->assertContains($expectedSectionLabel, $sectionHeadings, "[{$locale}] Missing Shield section heading [{$expectedSectionLabel}].");
            }

            foreach ($sectionHeadings as $sectionHeading) {
                $this->assertStringNotContainsString('And', $sectionHeading, "[{$locale}] Unexpected 'And' in Shield section [{$sectionHeading}].");
            }
        }
    }

    public function test_widgets_form_labels_and_action_buttons_switch_dynamically_between_id_and_en(): void
    {
        $this->actingAs($this->superAdmin);

        $article = Article::create([
            'title' => ['id' => 'Artikel Aksi', 'en' => 'Action Article'],
            'slug' => ['id' => 'artikel-aksi', 'en' => 'action-article'],
            'content' => ['id' => 'Isi artikel.', 'en' => 'Article content.'],
        ]);

        // Indonesian (default)
        App::setLocale('id');
        Livewire::test(FeedbackSectionWidget::class)
            ->assertSee('Kritik & Saran Pasien')
            ->assertSee('Belum ada kritik dan saran masuk.');

        Livewire::test(QuestionnaireSectionWidget::class)
            ->assertSee('Kuesioner Kepuasan Pasien');

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
            ->assertSee('Batal');

        // English (after session locale switch)
        App::setLocale('en');
        Livewire::test(FeedbackSectionWidget::class)
            ->assertSee('Patient Feedback & Suggestions')
            ->assertSee('No feedback or suggestions submitted yet.');

        Livewire::test(QuestionnaireSectionWidget::class)
            ->assertSee('Patient Satisfaction Questionnaires');

        $this->withSession([SetAdminPanelLocale::SESSION_KEY => 'en'])
            ->get(ArticleResource::getUrl('create'))
            ->assertOk()
            ->assertSee('Article Title (Indonesian)')
            ->assertSee('Create')
            ->assertSee('Cancel');

        $this->withSession([SetAdminPanelLocale::SESSION_KEY => 'en'])
            ->get(ArticleResource::getUrl('edit', ['record' => $article]))
            ->assertOk()
            ->assertSee('Article Title (Indonesian)')
            ->assertSee('Save')
            ->assertSee('Cancel');

        $this->withSession([SetAdminPanelLocale::SESSION_KEY => 'en'])
            ->get(RoleResource::getUrl('create'))
            ->assertOk()
            ->assertSee('Name')
            ->assertSee('Select All')
            ->assertSee('Create')
            ->assertSee('Cancel');

        App::setLocale('id');
    }

    public function test_route_accessibility_and_existing_slugs_across_panel_roles(): void
    {
        $this->assertStringEndsWith('/admin/facility-services', FacilityServiceResource::getUrl('index'));
        $this->assertStringEndsWith('/admin/room-facilities', RoomFacilityResource::getUrl('index'));
        $this->assertStringEndsWith('/admin/feedbacks/feedback', FeedbackResource::getUrl('index'));
        $this->assertStringEndsWith('/admin/shield/roles', RoleResource::getUrl('index'));

        $this->switchAuthenticatedUser($this->marketing);
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

        $this->switchAuthenticatedUser($this->staffIt);
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

    /**
     * @param  list<array{label: ?string, items: list<string>}>  $expectedGroups
     */
    private function assertSidebarDomStructure(
        string $html,
        array $expectedGroups,
        ?string $expectedActiveGroupLabel = null,
        ?string $expectedActiveItemLabel = null,
    ): void {
        $sidebarHtml = $this->extractSidebarNavigationHtml($html);
        $this->assertStringNotContainsString('&amp;amp;', $sidebarHtml);

        $dom = new DOMDocument;
        $previousErrorSetting = libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>'.$sidebarHtml);
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorSetting);

        $this->assertTrue($loaded, 'Failed to parse rendered sidebar HTML into DOMDocument.');

        $xpath = new DOMXPath($dom);

        $groupedBorders = $xpath->query('//*[contains(concat(" ", normalize-space(@class), " "), " fi-sidebar-item-grouped-border ")]');
        $this->assertNotFalse($groupedBorders);
        $this->assertSame(0, $groupedBorders->length, 'Submenu items must keep their icons without grouped border fallbacks.');

        $groupNodes = $xpath->query('//ul[contains(concat(" ", normalize-space(@class), " "), " fi-sidebar-nav-groups ")]/li[contains(concat(" ", normalize-space(@class), " "), " fi-sidebar-group ")]');
        $this->assertNotFalse($groupNodes);
        $this->assertSame(count($expectedGroups), $groupNodes->length, 'Rendered sidebar group count mismatch (unexpected or empty group rendered).');

        foreach ($expectedGroups as $index => $expectedGroup) {
            /** @var DOMElement $groupNode */
            $groupNode = $groupNodes->item($index);
            $expectedLabel = $expectedGroup['label'];
            $expectedItems = $expectedGroup['items'];

            $this->assertSame($expectedLabel ?? '', $groupNode->getAttribute('data-group-label'));

            $groupClasses = preg_split('/\s+/', trim($groupNode->getAttribute('class'))) ?: [];
            $this->assertContains('fi-sidebar-group', $groupClasses);
            $this->assertNotContains('fi-collapsed', $groupClasses, 'Sidebar group must not render collapsed initially.');

            $groupItemListNodes = $xpath->query('./ul[contains(concat(" ", normalize-space(@class), " "), " fi-sidebar-group-items ")]', $groupNode);
            $this->assertNotFalse($groupItemListNodes);
            $this->assertSame(1, $groupItemListNodes->length);
            /** @var DOMElement $groupItemListNode */
            $groupItemListNode = $groupItemListNodes->item(0);

            if ($expectedLabel === null) {
                $groupBtnNodes = $xpath->query('./div[contains(concat(" ", normalize-space(@class), " "), " fi-sidebar-group-btn ")]', $groupNode);
                $this->assertNotFalse($groupBtnNodes);
                $this->assertSame(0, $groupBtnNodes->length, 'Standalone Dashboard entry must remain ungrouped.');
            } else {
                $this->assertContains('fi-collapsible', $groupClasses, "Group [{$expectedLabel}] must be collapsible.");

                if ($expectedActiveGroupLabel !== null) {
                    if ($expectedLabel === $expectedActiveGroupLabel) {
                        $this->assertContains('fi-active', $groupClasses, "Expected group [{$expectedLabel}] to be active.");
                    } else {
                        $this->assertNotContains('fi-active', $groupClasses, "Group [{$expectedLabel}] should not be active.");
                    }
                }

                $this->assertStringContainsString(
                    'label: '.Js::from($expectedLabel)->toHtml(),
                    $groupNode->getAttribute('x-data'),
                    "Group [{$expectedLabel}] Alpine x-data label mismatch."
                );

                $groupBtnNodes = $xpath->query('./div[contains(concat(" ", normalize-space(@class), " "), " fi-sidebar-group-btn ")]', $groupNode);
                $this->assertNotFalse($groupBtnNodes);
                $this->assertSame(1, $groupBtnNodes->length);
                /** @var DOMElement $groupBtnNode */
                $groupBtnNode = $groupBtnNodes->item(0);

                $directGroupIcons = $xpath->query('./svg', $groupBtnNode);
                $this->assertNotFalse($directGroupIcons);
                $this->assertSame(0, $directGroupIcons->length, "Group [{$expectedLabel}] must not render a group-level icon.");

                $groupLabelNodes = $xpath->query('./span[contains(concat(" ", normalize-space(@class), " "), " fi-sidebar-group-label ")]', $groupBtnNode);
                $this->assertNotFalse($groupLabelNodes);
                $this->assertSame(1, $groupLabelNodes->length);
                $this->assertSame(
                    $expectedLabel,
                    trim((string) preg_replace('/\s+/', ' ', $groupLabelNodes->item(0)->textContent))
                );

                $collapseBtns = $xpath->query('./button[contains(concat(" ", normalize-space(@class), " "), " fi-sidebar-group-collapse-btn ")]', $groupBtnNode);
                $this->assertNotFalse($collapseBtns);
                $this->assertSame(1, $collapseBtns->length, "Group [{$expectedLabel}] must render a collapse toggle button.");
                /** @var DOMElement $collapseBtn */
                $collapseBtn = $collapseBtns->item(0);
                $this->assertSame(
                    $groupItemListNode->getAttribute('id'),
                    $collapseBtn->getAttribute('aria-controls'),
                    "Collapse button aria-controls must match group items <ul> id for [{$expectedLabel}]."
                );
                $this->assertSame(
                    '! $store.sidebar.groupIsCollapsed(label)',
                    $collapseBtn->getAttribute('x-bind:aria-expanded')
                );
            }

            $itemNodes = $xpath->query('./li[contains(concat(" ", normalize-space(@class), " "), " fi-sidebar-item ")]', $groupItemListNode);
            $this->assertNotFalse($itemNodes);
            $this->assertSame(
                count($expectedItems),
                $itemNodes->length,
                'Sidebar item count mismatch inside group ['.($expectedLabel ?? 'ungrouped').'].'
            );

            foreach ($expectedItems as $itemIndex => $expectedItemLabel) {
                /** @var DOMElement $itemNode */
                $itemNode = $itemNodes->item($itemIndex);
                $itemLabelNodes = $xpath->query('.//span[contains(concat(" ", normalize-space(@class), " "), " fi-sidebar-item-label ")]', $itemNode);
                $this->assertNotFalse($itemLabelNodes);
                $this->assertSame(1, $itemLabelNodes->length);
                $actualItemLabel = trim((string) preg_replace('/\s+/', ' ', $itemLabelNodes->item(0)->textContent));
                $this->assertSame($expectedItemLabel, $actualItemLabel);

                $itemIcons = $xpath->query('.//svg[contains(concat(" ", normalize-space(@class), " "), " fi-sidebar-item-icon ")]', $itemNode);
                $this->assertNotFalse($itemIcons);
                $this->assertSame(1, $itemIcons->length, "Submenu item [{$expectedItemLabel}] must render exactly one SVG icon.");

                if ($expectedActiveItemLabel !== null) {
                    $itemClasses = preg_split('/\s+/', trim($itemNode->getAttribute('class'))) ?: [];
                    $linkNodes = $xpath->query('./a', $itemNode);
                    $this->assertNotFalse($linkNodes);
                    $this->assertSame(1, $linkNodes->length);
                    /** @var DOMElement $linkNode */
                    $linkNode = $linkNodes->item(0);

                    if ($expectedItemLabel === $expectedActiveItemLabel) {
                        $this->assertContains('fi-active', $itemClasses, "Expected item [{$expectedItemLabel}] to be active.");
                        $this->assertSame('page', $linkNode->getAttribute('aria-current'));
                    } else {
                        $this->assertNotContains('fi-active', $itemClasses, "Item [{$expectedItemLabel}] should not be active.");
                        $this->assertSame('', $linkNode->getAttribute('aria-current'));
                    }
                }
            }
        }
    }

    private function switchAuthenticatedUser(User $user): void
    {
        session()->flush();
        app()->forgetScopedInstances();
        App::setLocale('id');
        $this->actingAs($user);
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

    private function extractSidebarNavigationHtml(string $html): string
    {
        if (! preg_match('/<nav[^>]*class="[^"]*fi-sidebar-nav[^"]*"[^>]*>(.*?)<\/nav>/s', $html, $matches)) {
            $this->fail('Could not find <nav class="fi-sidebar-nav"> in rendered HTML.');
        }

        return $matches[1];
    }

    /**
     * @return list<string>
     */
    private function extractSidebarItemLabels(string $sidebarHtml): array
    {
        preg_match_all(
            '/<span[^>]*class="[^"]*fi-sidebar-item-label[^"]*"[^>]*>(.*?)<\/span>/s',
            $sidebarHtml,
            $matches
        );

        return array_values(array_map(
            fn (string $label): string => trim((string) preg_replace(
                '/\s+/',
                ' ',
                html_entity_decode(strip_tags($label), ENT_QUOTES | ENT_HTML5, 'UTF-8')
            )),
            $matches[1]
        ));
    }

    private function extractSidebarNavigationText(string $html): string
    {
        $sidebarHtml = $this->extractSidebarNavigationHtml($html);
        $withoutScripts = (string) preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $sidebarHtml);
        $decoded = html_entity_decode(strip_tags($withoutScripts), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace('/\s+/', ' ', $decoded));
    }
}
