<?php

namespace Tests\Feature;

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

    public function test_resource_navigation_plural_and_singular_labels_match_standard(): void
    {
        $expected = [
            ArticleResource::class => [
                'nav' => 'Articles',
                'plural' => 'Articles',
                'singular' => 'Article',
                'group' => 'Marketing & Editorial',
                'sort' => 1,
            ],
            BannerPromotionResource::class => [
                'nav' => 'Banner Promotions',
                'plural' => 'Banner Promotions',
                'singular' => 'Banner Promotion',
                'group' => 'Marketing & Editorial',
                'sort' => 2,
            ],
            NewsResource::class => [
                'nav' => 'News',
                'plural' => 'News',
                'singular' => 'News',
                'group' => 'Marketing & Editorial',
                'sort' => 3,
            ],
            PromotionResource::class => [
                'nav' => 'Promotions',
                'plural' => 'Promotions',
                'singular' => 'Promotion',
                'group' => 'Marketing & Editorial',
                'sort' => 4,
            ],
            FacilityServiceResource::class => [
                'nav' => 'Facilities & Services',
                'plural' => 'Facilities & Services',
                'singular' => 'Facility & Service',
                'group' => 'Services & Rooms',
                'sort' => 1,
            ],
            RoomFacilityResource::class => [
                'nav' => 'Inpatient Rooms',
                'plural' => 'Inpatient Rooms',
                'singular' => 'Inpatient Room',
                'group' => 'Services & Rooms',
                'sort' => 2,
            ],
            DoctorResource::class => [
                'nav' => 'Doctor Profiles',
                'plural' => 'Doctor Profiles',
                'singular' => 'Doctor Profile',
                'group' => 'Doctors & Library',
                'sort' => 1,
            ],
            OnlineLibraryResource::class => [
                'nav' => 'Online Library',
                'plural' => 'Online Library',
                'singular' => 'Online Library',
                'group' => 'Doctors & Library',
                'sort' => 2,
            ],
            FeedbackResource::class => [
                'nav' => 'Feedback & Suggestions',
                'plural' => 'Feedback & Suggestions',
                'singular' => 'Feedback',
                'group' => 'Patient Feedback',
                'sort' => 1,
            ],
            QuestionnaireResource::class => [
                'nav' => 'Satisfaction Questionnaires',
                'plural' => 'Satisfaction Questionnaires',
                'singular' => 'Satisfaction Questionnaire',
                'group' => 'Patient Feedback',
                'sort' => 2,
            ],
            DynamicFormResource::class => [
                'nav' => 'Custom Questionnaire Form',
                'plural' => 'Custom Questionnaire Forms',
                'singular' => 'Custom Questionnaire Form',
                'group' => 'Patient Feedback',
                'sort' => 3,
            ],
            DynamicFormSubmissionResource::class => [
                'nav' => 'Custom Questionnaire Responses',
                'plural' => 'Custom Questionnaire Responses',
                'singular' => 'Custom Questionnaire Response',
                'group' => 'Patient Feedback',
                'sort' => 4,
            ],
            UserResource::class => [
                'nav' => 'User Management',
                'plural' => 'Users',
                'singular' => 'User',
                'group' => 'Access Control',
                'sort' => 1,
            ],
            RoleResource::class => [
                'nav' => 'Roles',
                'plural' => 'Roles',
                'singular' => 'Role',
                'group' => 'Access Control',
                'sort' => 2,
            ],
        ];

        $this->actingAs($this->superAdmin);

        $this->assertSame('Dashboard', Dashboard::getNavigationLabel());
        $this->assertSame('Dashboard', (new Dashboard)->getTitle());
        $this->assertNull(Dashboard::getNavigationGroup());

        foreach ($expected as $resource => $labels) {
            $this->assertSame($labels['nav'], $resource::getNavigationLabel(), "{$resource} navigationLabel mismatch");
            $this->assertSame($labels['plural'], $resource::getTitleCasePluralModelLabel(), "{$resource} pluralModelLabel mismatch");
            $this->assertSame($labels['singular'], $resource::getTitleCaseModelLabel(), "{$resource} modelLabel mismatch");
            $this->assertSame($labels['group'], $resource::getNavigationGroup(), "{$resource} navigationGroup mismatch");
            $this->assertSame($labels['sort'], $resource::getNavigationSort(), "{$resource} navigationSort mismatch");
        }
    }

    public function test_super_admin_sidebar_navigation_groups_labels_and_order(): void
    {
        $this->actingAs($this->superAdmin);

        $panel = Filament::getPanel('admin');
        $groups = array_values($panel->getNavigation());

        $this->assertCount(6, $groups);

        $expectedGroups = [
            [
                'label' => null,
                'items' => ['Dashboard'],
            ],
            [
                'label' => 'Marketing & Editorial',
                'items' => ['Articles', 'Banner Promotions', 'News', 'Promotions'],
            ],
            [
                'label' => 'Services & Rooms',
                'items' => ['Facilities & Services', 'Inpatient Rooms'],
            ],
            [
                'label' => 'Doctors & Library',
                'items' => ['Doctor Profiles', 'Online Library'],
            ],
            [
                'label' => 'Patient Feedback',
                'items' => ['Feedback & Suggestions', 'Satisfaction Questionnaires', 'Custom Questionnaire Form', 'Custom Questionnaire Responses'],
            ],
            [
                'label' => 'Access Control',
                'items' => ['User Management', 'Roles'],
            ],
        ];

        foreach ($expectedGroups as $index => $expectedGroup) {
            /** @var NavigationGroup $group */
            $group = $groups[$index];
            $this->assertSame($expectedGroup['label'], $group->getLabel(), "Navigation group label mismatch at index {$index}");

            if ($expectedGroup['label'] !== null) {
                $this->assertTrue($group->isCollapsible(), "Group [{$expectedGroup['label']}] should be collapsible.");
                $this->assertFalse($group->isCollapsed(), "Group [{$expectedGroup['label']}] should be expanded by default.");
                $this->assertNull($group->getIcon(), "Group [{$expectedGroup['label']}] should not define a group icon.");
            }

            $items = collect($group->getItems())->values();
            $this->assertSame(
                $expectedGroup['items'],
                $items->map(fn (NavigationItem $item): string => $item->getLabel())->all(),
                "Navigation items mismatch for group [{$expectedGroup['label']}]"
            );

            foreach ($items as $item) {
                $this->assertNotNull(
                    $item->getIcon(),
                    "Submenu item [{$item->getLabel()}] should retain its icon."
                );
            }
        }

        $response = $this->get('/admin')->assertOk();
        $html = $response->getContent();
        $this->assertSame('Dashboard', $this->extractPageHeading($html));
        $this->assertSame($this->expectedDocumentTitle('Dashboard'), $this->extractPageTitle($html));

        $response->assertSeeInOrder([
            'Dashboard',
            'Marketing & Editorial',
            'Articles',
            'Banner Promotions',
            'News',
            'Promotions',
            'Services & Rooms',
            'Facilities & Services',
            'Inpatient Rooms',
            'Doctors & Library',
            'Doctor Profiles',
            'Online Library',
            'Patient Feedback',
            'Feedback & Suggestions',
            'Satisfaction Questionnaires',
            'Custom Questionnaire Form',
            'Custom Questionnaire Responses',
            'Access Control',
            'User Management',
            'Roles',
        ]);

        $sidebarHtml = $this->extractSidebarNavigationHtml($html);
        foreach ([
            'Marketing &amp; Editorial',
            'Services &amp; Rooms',
            'Doctors &amp; Library',
            'Patient Feedback',
            'Access Control',
        ] as $encodedGroupLabel) {
            $this->assertSame(
                1,
                preg_match(
                    '/<li\b[^>]*data-group-label="'.preg_quote($encodedGroupLabel, '/').'"[^>]*\sclass="([^"]*)"/s',
                    $sidebarHtml,
                    $groupAttrMatches
                ),
                "Expected collapsible sidebar group [{$encodedGroupLabel}] in rendered HTML."
            );
            $groupClasses = preg_split('/\s+/', trim($groupAttrMatches[1])) ?: [];
            $this->assertContains('fi-sidebar-group', $groupClasses);
            $this->assertContains('fi-collapsible', $groupClasses);
            $this->assertNotContains(
                'fi-collapsed',
                $groupClasses,
                "Group [{$encodedGroupLabel}] should not render as collapsed initially."
            );
        }

        $this->assertStringContainsString('JSON.stringify([])', $sidebarHtml);
        $this->assertStringNotContainsString('&amp;amp;', $sidebarHtml);

        $renderedItemLabels = $this->extractSidebarItemLabels($sidebarHtml);
        $this->assertSame([
            'Dashboard',
            'Articles',
            'Banner Promotions',
            'News',
            'Promotions',
            'Facilities & Services',
            'Inpatient Rooms',
            'Doctor Profiles',
            'Online Library',
            'Feedback & Suggestions',
            'Satisfaction Questionnaires',
            'Custom Questionnaire Form',
            'Custom Questionnaire Responses',
            'User Management',
            'Roles',
        ], $renderedItemLabels);

        $this->assertSame(
            count($renderedItemLabels),
            preg_match_all('/<svg[^>]*class="[^"]*fi-sidebar-item-icon[^"]*"/s', $sidebarHtml),
            'Every sidebar navigation item must render its submenu icon SVG.'
        );

        $this->assertSidebarDomStructure($html, $expectedGroups, null, 'Dashboard');

        foreach ([
            '/admin/articles' => ['Marketing & Editorial', 'Articles'],
            '/admin/facility-services' => ['Services & Rooms', 'Facilities & Services'],
            '/admin/doctors' => ['Doctors & Library', 'Doctor Profiles'],
            '/admin/feedbacks/feedback' => ['Patient Feedback', 'Feedback & Suggestions'],
            '/admin/users' => ['Access Control', 'User Management'],
            '/admin/shield/roles' => ['Access Control', 'Roles'],
        ] as $route => [$activeGroup, $activeItem]) {
            app()->forgetScopedInstances();
            $routeHtml = $this->get($route)->assertOk()->getContent();
            $this->assertSidebarDomStructure($routeHtml, $expectedGroups, $activeGroup, $activeItem);
        }

        foreach ([
            'Dasbor',
            'Profil Dokter',
            'Facility Services',
            'Facilities And Services',
            'Room Facilities',
            'Perpustakaan Online',
            'Manajemen User',
            'Administration',
            'Administrasi',
            'Filament Shield',
            'Konten Halaman Utama',
            'Marketing And Editorial',
            'Services And Rooms',
            'Doctors And Library',
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
            'Administration',
            'Administrasi',
            'Filament Shield',
            'Konten Halaman Utama',
            'Marketing And Editorial',
            'Services And Rooms',
            'Doctors And Library',
            'Peran',
            'Daftar Peran',
        ] as $forbiddenSidebarLabel) {
            $this->assertStringNotContainsString($forbiddenSidebarLabel, $sidebarText);
        }
        $this->assertStringNotContainsStringIgnoringCase('peran', $sidebarText);
    }

    public function test_role_based_sidebar_navigation_visibility_hides_inaccessible_and_empty_groups(): void
    {
        $panelOnlyRole = Role::findOrCreate('panel_user', 'web');
        $panelOnlyUser = User::factory()->create(['must_change_password' => false]);
        $panelOnlyUser->assignRole($panelOnlyRole);

        $expectedStaffGroups = [
            [
                'label' => null,
                'items' => ['Dashboard'],
            ],
            [
                'label' => 'Marketing & Editorial',
                'items' => ['Articles', 'Banner Promotions', 'News', 'Promotions'],
            ],
            [
                'label' => 'Services & Rooms',
                'items' => ['Facilities & Services', 'Inpatient Rooms'],
            ],
            [
                'label' => 'Doctors & Library',
                'items' => ['Doctor Profiles', 'Online Library'],
            ],
            [
                'label' => 'Patient Feedback',
                'items' => ['Feedback & Suggestions', 'Satisfaction Questionnaires', 'Custom Questionnaire Form', 'Custom Questionnaire Responses'],
            ],
        ];

        foreach ([$this->marketing, $this->staffIt] as $staffUser) {
            $this->switchAuthenticatedUser($staffUser);

            $panel = Filament::getPanel('admin');
            $groups = array_values($panel->getNavigation());
            $groupLabels = collect($groups)->map(fn (NavigationGroup $group): ?string => $group->getLabel())->all();

            $this->assertSame([
                null,
                'Marketing & Editorial',
                'Services & Rooms',
                'Doctors & Library',
                'Patient Feedback',
            ], $groupLabels);

            foreach ($groups as $group) {
                $this->assertNotEmpty($group->getItems(), "Group [{$group->getLabel()}] must not be empty.");
            }

            $response = $this->get('/admin')->assertOk();
            $html = $response->getContent();
            $this->assertSame('Dashboard', $this->extractPageHeading($html));
            $this->assertSame($this->expectedDocumentTitle('Dashboard'), $this->extractPageTitle($html));
            $this->assertSidebarDomStructure($html, $expectedStaffGroups, null, 'Dashboard');

            $sidebarText = $this->extractSidebarNavigationText($html);
            $this->assertStringContainsString('Marketing & Editorial', $sidebarText);
            $this->assertStringContainsString('Services & Rooms', $sidebarText);
            $this->assertStringContainsString('Doctors & Library', $sidebarText);
            $this->assertStringContainsString('Patient Feedback', $sidebarText);
            $this->assertStringNotContainsString('Access Control', $sidebarText);
            $this->assertStringNotContainsString('Administration', $sidebarText);
            $this->assertStringNotContainsString('Filament Shield', $sidebarText);
            $response->assertDontSee('Access Control');
            $response->assertDontSee('Administration');
            $response->assertDontSee('User Management');
            $response->assertDontSee('Peran');
            $response->assertDontSee('Daftar Peran');
            $response->assertDontSee('Dasbor');
        }

        // panel_user hanya melihat Dashboard dan Patient Feedback (via PatientSubmissionPolicy).
        $this->switchAuthenticatedUser($panelOnlyUser);

        $panel = Filament::getPanel('admin');
        $panelUserGroups = array_values($panel->getNavigation());
        $panelUserGroupLabels = collect($panelUserGroups)->map(fn (NavigationGroup $group): ?string => $group->getLabel())->all();

        $expectedPanelUserGroups = [
            [
                'label' => null,
                'items' => ['Dashboard'],
            ],
            [
                'label' => 'Patient Feedback',
                'items' => ['Feedback & Suggestions', 'Satisfaction Questionnaires'],
            ],
        ];

        $this->assertSame([null, 'Patient Feedback'], $panelUserGroupLabels);
        $this->assertSame(
            ['Dashboard'],
            collect($panelUserGroups[0]->getItems())->map(fn (NavigationItem $item): string => $item->getLabel())->values()->all()
        );
        $this->assertSame(
            ['Feedback & Suggestions', 'Satisfaction Questionnaires'],
            collect($panelUserGroups[1]->getItems())->map(fn (NavigationItem $item): string => $item->getLabel())->values()->all()
        );

        $panelUserResponse = $this->get('/admin')->assertOk();
        $panelUserHtml = $panelUserResponse->getContent();
        $this->assertSame('Dashboard', $this->extractPageHeading($panelUserHtml));
        $this->assertSame($this->expectedDocumentTitle('Dashboard'), $this->extractPageTitle($panelUserHtml));
        $this->assertSidebarDomStructure($panelUserHtml, $expectedPanelUserGroups, null, 'Dashboard');

        $panelUserSidebarHtml = $this->extractSidebarNavigationHtml($panelUserHtml);
        $this->assertSame(
            ['Dashboard', 'Feedback & Suggestions', 'Satisfaction Questionnaires'],
            $this->extractSidebarItemLabels($panelUserSidebarHtml)
        );
        $this->assertSame(1, preg_match_all('/<li\b[^>]*\bdata-group-label="([^"]+)"/s', $panelUserSidebarHtml, $panelUserRenderedGroups));
        $this->assertSame(['Patient Feedback'], $panelUserRenderedGroups[1]);

        $panelUserSidebarText = $this->extractSidebarNavigationText($panelUserHtml);
        $this->assertStringContainsString('Dashboard', $panelUserSidebarText);
        $this->assertStringContainsString('Patient Feedback', $panelUserSidebarText);
        $this->assertStringContainsString('Feedback & Suggestions', $panelUserSidebarText);
        $this->assertStringContainsString('Satisfaction Questionnaires', $panelUserSidebarText);

        foreach ([
            'Marketing & Editorial',
            'Services & Rooms',
            'Doctors & Library',
            'Access Control',
            'Administration',
            'Filament Shield',
            'Articles',
            'Banner Promotions',
            'News',
            'Promotions',
            'Facilities & Services',
            'Inpatient Rooms',
            'Doctor Profiles',
            'Online Library',
            'User Management',
            'Roles',
            'Peran',
            'Daftar Peran',
            'Dasbor',
        ] as $hiddenForPanelUser) {
            $this->assertStringNotContainsString($hiddenForPanelUser, $panelUserSidebarText);
            $panelUserResponse->assertDontSee($hiddenForPanelUser);
        }

        app()->forgetScopedInstances();
        $panelUserFeedbackHtml = $this->get('/admin/feedbacks/feedback')->assertOk()->getContent();
        $this->assertSidebarDomStructure(
            $panelUserFeedbackHtml,
            $expectedPanelUserGroups,
            'Patient Feedback',
            'Feedback & Suggestions'
        );
        app()->forgetScopedInstances();
        $panelUserQuestionnaireHtml = $this->get('/admin/questionnaires')->assertOk()->getContent();
        $this->assertSidebarDomStructure(
            $panelUserQuestionnaireHtml,
            $expectedPanelUserGroups,
            'Patient Feedback',
            'Satisfaction Questionnaires'
        );
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

        $expectedPartialGroups = [
            [
                'label' => null,
                'items' => ['Dashboard'],
            ],
            [
                'label' => 'Marketing & Editorial',
                'items' => ['Articles'],
            ],
            [
                'label' => 'Patient Feedback',
                'items' => ['Feedback & Suggestions', 'Satisfaction Questionnaires'],
            ],
        ];

        $partialNavGroups = array_values(Filament::getPanel('admin')->getNavigation());
        $this->assertSame(
            [null, 'Marketing & Editorial', 'Patient Feedback'],
            collect($partialNavGroups)->map(fn (NavigationGroup $group): ?string => $group->getLabel())->all()
        );
        $partialDashboardHtml = $this->get('/admin')->assertOk()->getContent();
        $this->assertSidebarDomStructure($partialDashboardHtml, $expectedPartialGroups, null, 'Dashboard');
        $partialSidebarText = $this->extractSidebarNavigationText($partialDashboardHtml);
        foreach ([
            'Banner Promotions',
            'News',
            'Promotions',
            'Services & Rooms',
            'Doctors & Library',
            'Access Control',
        ] as $hiddenForPartialUser) {
            $this->assertStringNotContainsString($hiddenForPartialUser, $partialSidebarText);
        }

        // Pemberian permission User/Role langsung ke non-super_admin tetap tidak membuka grup Access Control.
        $panelOnlyUser->givePermissionTo(['ViewAny:User', 'ViewAny:Role']);
        $this->switchAuthenticatedUser($panelOnlyUser);
        $groupsAfterDirectPerm = array_values(Filament::getPanel('admin')->getNavigation());
        $this->assertSame(
            [null, 'Patient Feedback'],
            collect($groupsAfterDirectPerm)->map(fn (NavigationGroup $group): ?string => $group->getLabel())->all()
        );
        $panelUserAfterPermHtml = $this->get('/admin')->assertOk()->getContent();
        $this->assertSidebarDomStructure($panelUserAfterPermHtml, $expectedPanelUserGroups, null, 'Dashboard');

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
        $this->assertStringNotContainsString('Administration', $sidebarText);
        $this->assertStringNotContainsString('Administrasi', $sidebarText);

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
            'Administration',
            'Administrasi',
            'Filament Shield',
            'Konten Halaman Utama',
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
