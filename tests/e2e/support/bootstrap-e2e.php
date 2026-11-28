<?php

declare(strict_types=1);

/**
 * Bootstrap database terisolasi khusus Playwright E2E testing.
 *
 * ATURAN KEAMANAN:
 * - Hanya diizinkan berjalan bila APP_ENV=testing dan PLAYWRIGHT_E2E_BOOTSTRAP=1.
 * - Menolak keras menyentuh database lokal/produksi (rs_elisabeth_cms_db atau pgsql tanpa opt-in).
 * - Tidak pernah memodifikasi PermissionSeeder produksi; role & akun uji dibentuk
 *   di dalam database E2E terisolasi mengikuti pola Tests\Concerns\SeedsTestRolesAndUsers.
 */

use App\Enums\DynamicFormMode;
use App\Enums\DynamicFormStatus;
use App\Http\Requests\Api\Cms\StoreFeedbackApiRequest;
use App\Models\Article;
use App\Models\Doctor;
use App\Models\DynamicForm;
use App\Models\Feedback;
use App\Models\News;
use App\Models\Promotion;
use App\Models\QuestionnaireResponse;
use App\Models\Role;
use App\Models\User;
use App\Services\DynamicForm\DynamicFormVersionService;
use App\Support\QuestionnaireDefinition;
use Database\Seeders\PermissionSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

$rootDir = dirname(__DIR__, 3);
require $rootDir.'/vendor/autoload.php';

$appEnv = (string) (getenv('APP_ENV') ?: '');
$bootstrapFlag = (string) (getenv('PLAYWRIGHT_E2E_BOOTSTRAP') ?: '');
$dbConnection = (string) (getenv('DB_CONNECTION') ?: '');
$dbDatabase = (string) (getenv('DB_DATABASE') ?: '');

if (! in_array($appEnv, ['playwright', 'testing'], true) || $bootstrapFlag !== '1') {
    fwrite(STDERR, "[E2E Bootstrap] ABORTED: Requires APP_ENV=playwright (or testing) and PLAYWRIGHT_E2E_BOOTSTRAP=1.\n");
    exit(1);
}

if ($dbDatabase === '' || str_contains(strtolower($dbDatabase), 'rs_elisabeth_cms_db')) {
    fwrite(STDERR, "[E2E Bootstrap] ABORTED: Refusing to touch development/production database ({$dbDatabase}).\n");
    exit(1);
}

if ($dbConnection === 'pgsql' && getenv('PLAYWRIGHT_ALLOW_PGSQL_RESET') !== 'true') {
    fwrite(STDERR, "[E2E Bootstrap] ABORTED: Refusing to reset PostgreSQL without PLAYWRIGHT_ALLOW_PGSQL_RESET=true.\n");
    exit(1);
}

if ($dbConnection === 'sqlite' && $dbDatabase !== ':memory:') {
    $dbDir = dirname($dbDatabase);
    if (! is_dir($dbDir)) {
        mkdir($dbDir, 0775, true);
    }
    if (file_exists($dbDatabase)) {
        unlink($dbDatabase);
    }
    touch($dbDatabase);
}

/** @var Application $app */
$app = require $rootDir.'/bootstrap/app.php';
$kernel = $app->make(Kernel::class);
$kernel->bootstrap();

// Verifikasi ulang di dalam container Laravel (mencegah kebocoran config cache).
if (! in_array($app->environment(), ['playwright', 'testing'], true) || config('database.default') !== $dbConnection) {
    fwrite(STDERR, "[E2E Bootstrap] ABORTED: Resolved Laravel env/connection mismatch.\n");
    exit(1);
}

$exitMigrate = Artisan::call('migrate:fresh', ['--force' => true]);
if ($exitMigrate !== 0) {
    fwrite(STDERR, Artisan::output());
    exit($exitMigrate);
}

$exitSeed = Artisan::call('db:seed', ['--class' => PermissionSeeder::class, '--force' => true]);
if ($exitSeed !== 0) {
    fwrite(STDERR, Artisan::output());
    exit($exitSeed);
}

app(PermissionRegistrar::class)->forgetCachedPermissions();

// 1. Bentuk role & permission uji (setara Tests\Concerns\SeedsTestRolesAndUsers).
$contentSubjects = [
    'Article',
    'News',
    'Promotion',
    'BannerPromotion',
    'FacilityService',
    'RoomFacility',
    'OnlineLibrary',
    'DynamicForm',
];
$reorderableSubjects = ['BannerPromotion', 'FacilityService', 'RoomFacility'];
$widgetSubjects = ['FeedbackSectionWidget', 'QuestionnaireSectionWidget', 'DynamicFeedbackOverviewWidget'];
$patientSubjects = ['Feedback', 'QuestionnaireResponse', 'DynamicFormSubmission'];

$superAdminRole = Role::findOrCreate(Role::SUPER_ADMIN, 'web');
$superAdminRole->syncPermissions(Permission::where('guard_name', 'web')->get());

$marketingPermissions = [];
foreach ($contentSubjects as $subject) {
    foreach (['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'] as $action) {
        $marketingPermissions[] = "{$action}:{$subject}";
    }
}
$marketingPermissions[] = 'ViewAny:Doctor';
$marketingPermissions[] = 'View:Doctor';
$marketingPermissions[] = 'Update:Doctor';
foreach ($patientSubjects as $subject) {
    $marketingPermissions[] = "ViewAny:{$subject}";
    $marketingPermissions[] = "View:{$subject}";
}
foreach ($reorderableSubjects as $subject) {
    $marketingPermissions[] = "Reorder:{$subject}";
}
foreach ($widgetSubjects as $subject) {
    $marketingPermissions[] = "View:{$subject}";
}

$marketingRole = Role::findOrCreate('marketing', 'web');
$marketingRole->syncPermissions(Permission::whereIn('name', $marketingPermissions)->get());

$staffItPermissions = ['ViewAny:Doctor', 'View:Doctor'];
foreach ($contentSubjects as $subject) {
    $staffItPermissions[] = "ViewAny:{$subject}";
    $staffItPermissions[] = "View:{$subject}";
}
foreach ($patientSubjects as $subject) {
    $staffItPermissions[] = "ViewAny:{$subject}";
    $staffItPermissions[] = "View:{$subject}";
}
foreach ($widgetSubjects as $subject) {
    $staffItPermissions[] = "View:{$subject}";
}
// Staff IT uji memiliki akses baca System & Integrations Monitor (tanpa Trigger:WebCacheInvalidation)
$staffItPermissions[] = 'View:SystemMonitor';

$staffItRole = Role::findOrCreate('Staff IT', 'web');
$staffItRole->syncPermissions(Permission::whereIn('name', $staffItPermissions)->get());

// 2. Buat 4 akun uji terisolasi (termasuk 1 akun wajib ganti password).
$superAdmin = User::create([
    'name' => 'E2E Super Admin',
    'email' => getenv('PLAYWRIGHT_SUPER_ADMIN_EMAIL') ?: 'super-admin@e2e.local',
    'password' => Hash::make(getenv('PLAYWRIGHT_SUPER_ADMIN_PASSWORD') ?: 'E2eSuperAdmin!2026'),
    'must_change_password' => false,
    'is_active' => true,
]);
$superAdmin->assignRole($superAdminRole);

$marketing = User::create([
    'name' => 'E2E Marketing Staff',
    'email' => getenv('PLAYWRIGHT_MARKETING_EMAIL') ?: 'marketing@e2e.local',
    'password' => Hash::make(getenv('PLAYWRIGHT_MARKETING_PASSWORD') ?: 'E2eMarketing!2026'),
    'must_change_password' => false,
    'is_active' => true,
]);
$marketing->assignRole($marketingRole);

$staffIt = User::create([
    'name' => 'E2E Staff IT',
    'email' => getenv('PLAYWRIGHT_STAFF_IT_EMAIL') ?: 'it@e2e.local',
    'password' => Hash::make(getenv('PLAYWRIGHT_STAFF_IT_PASSWORD') ?: 'E2eStaffIt!2026'),
    'must_change_password' => false,
    'is_active' => true,
]);
$staffIt->assignRole($staffItRole);

$forcedUser = User::create([
    'name' => 'E2E Forced Password User',
    'email' => getenv('PLAYWRIGHT_FORCED_EMAIL') ?: 'forced@e2e.local',
    'password' => Hash::make(getenv('PLAYWRIGHT_FORCED_PASSWORD') ?: 'E2eForced!2026'),
    'must_change_password' => true,
    'is_active' => true,
]);
$forcedUser->assignRole($marketingRole);

// 3. Buat fixture gambar 16:9 PNG sementara di tests/e2e/.auth/sample-banner.png (gitignored).
$authDir = $rootDir.'/tests/e2e/.auth';
if (! is_dir($authDir)) {
    mkdir($authDir, 0775, true);
}
$sampleImagePath = $authDir.'/sample-banner.png';
if (function_exists('imagecreatetruecolor')) {
    $img = imagecreatetruecolor(320, 180);
    if ($img !== false) {
        $navy = imagecolorallocate($img, 9, 89, 136);
        imagefilledrectangle($img, 0, 0, 319, 179, $navy);
        imagepng($img, $sampleImagePath);
        imagedestroy($img);
    }
}

$sampleNewsImage = null;
$sampleArticleImage = null;
if (file_exists($sampleImagePath)) {
    $pngBytes = file_get_contents($sampleImagePath);
    if ($pngBytes !== false) {
        Storage::disk('public')->put('news/e2e-sample.png', $pngBytes);
        Storage::disk('public')->put('articles/e2e-sample.png', $pngBytes);
        $sampleNewsImage = 'news/e2e-sample.png';
        $sampleArticleImage = 'articles/e2e-sample.png';
    }
}

// 4. Isi data sampel realistis lintas domain agar seluruh halaman list/detail/dashboard teruji.
Article::create([
    'title' => ['id' => 'Artikel Kesehatan Sampel E2E', 'en' => 'E2E Sample Health Article'],
    'slug' => ['id' => 'artikel-kesehatan-sampel-e2e', 'en' => 'e2e-sample-health-article'],
    'image_path' => $sampleArticleImage,
    'shorts' => ['id' => 'Ringkasan artikel sampel E2E.', 'en' => 'Summary of E2E sample article.'],
    'content' => ['id' => '<p>Konten artikel kesehatan sampel untuk pengujian UI.</p>', 'en' => '<p>Sample health article body for UI testing.</p>'],
    'tags' => ['id' => 'Kesehatan', 'en' => 'Health'],
    'author' => 'Tim Marketing RS',
    'is_active' => true,
]);

News::create([
    'title' => ['id' => 'Berita Rumah Sakit Sampel E2E', 'en' => 'E2E Sample Hospital News'],
    'slug' => ['id' => 'berita-rumah-sakit-sampel-e2e', 'en' => 'e2e-sample-hospital-news'],
    'image_path' => $sampleNewsImage,
    'shorts' => ['id' => 'Ringkasan berita sampel E2E.', 'en' => 'Summary of E2E sample news.'],
    'content' => ['id' => '<p>Isi berita rumah sakit sampel.</p>', 'en' => '<p>Sample hospital news content.</p>'],
    'category' => ['id' => 'Pengumuman', 'en' => 'Announcement'],
    'author' => 'Humas RS',
    'is_published' => true,
]);

Promotion::create([
    'title' => ['id' => 'Promo MCU Sampel E2E', 'en' => 'E2E Sample MCU Promotion'],
    'slug' => ['id' => 'promo-mcu-sampel-e2e', 'en' => 'e2e-sample-mcu-promotion'],
    'shorts' => ['id' => 'Paket pemeriksaan kesehatan berkala.', 'en' => 'Periodic medical checkup package.'],
    'description' => ['id' => '<p>Detail paket MCU rumah sakit.</p>', 'en' => '<p>Hospital MCU package details.</p>'],
    'start_date' => now()->subDay()->toDateString(),
    'end_date' => now()->addDays(14)->toDateString(),
    'is_active' => true,
]);

Doctor::create([
    'paramedic_code' => 'E2E-DOC-001',
    'name' => 'dr. Sampel Elisabeth, Sp.PD',
    'specialty_code' => 'KSM01',
    'specialty_name' => 'Penyakit Dalam',
    'service_unit_name' => 'Poliklinik Penyakit Dalam',
    'is_active' => true,
]);

Feedback::create([
    'ticket_number' => 'FB-20261127-E2E001',
    'reporter_name' => 'E2E-PII-REPORTER-SECRET',
    'relation_to_patient' => StoreFeedbackApiRequest::RELATIONS[0],
    'patient_name' => 'E2E-PII-PATIENT-SECRET',
    'phone_number' => '089998887776',
    'categories' => [StoreFeedbackApiRequest::CATEGORIES[0]],
    'complaint_detail' => 'Detail keluhan sampel untuk verifikasi privasi PII dan tindak lanjut super admin.',
    'expected_solution' => 'Peningkatan kecepatan koordinasi antrean poliklinik.',
    'locale' => 'id',
    'source' => 'web_public',
    'submitted_at' => now(),
    'status' => 'baru',
]);

$outpatientRatings = [];
foreach (QuestionnaireDefinition::QUESTIONS['rawat-jalan'] as $code => $scale) {
    $outpatientRatings[$code] = QuestionnaireDefinition::SCALES[$scale][2];
}

QuestionnaireResponse::create([
    'questionnaire_type' => 'rawat-jalan',
    'age_group' => '15 - 64 tahun',
    'gender' => 'Perempuan',
    'payment_method' => 'BPJS Kesehatan',
    'ratings' => $outpatientRatings,
    'suggestions' => 'Pelayanan ramah dan teratur.',
    'locale' => 'id',
    'source' => 'qr_barcode',
    'submitted_at' => now(),
]);

/** @var DynamicFormVersionService $versionService */
$versionService = app(DynamicFormVersionService::class);
$dynamicForm = DynamicForm::create([
    'title' => 'Survei Kepuasan E2E',
    'slug' => 'survei-kepuasan-e2e',
    'description' => '<p>Formulir survei dinamis sampel untuk pengujian Share &amp; QR Code.</p>',
    'mode' => DynamicFormMode::Questionnaire,
    'locale' => 'id',
    'status' => DynamicFormStatus::Draft,
    'allow_multiple_submissions' => true,
    'is_public_listed' => true,
]);

$versionService->syncDraftStructure($dynamicForm, [
    [
        'section_key' => 'sec_utama',
        'title' => 'Penilaian Layanan',
        'description' => 'Bagian utama penilaian pasien.',
        'fields' => [
            [
                'field_key' => 'rating_pelayanan',
                'type' => 'rating',
                'label' => 'Bagaimana penilaian Anda terhadap pelayanan kami?',
                'is_required' => true,
                'options' => ['max_stars' => 5, 'icon' => 'star'],
            ],
            [
                'field_key' => 'saran_singkat',
                'type' => 'short_text',
                'label' => 'Saran singkat',
                'is_required' => false,
                'options' => ['subtype' => 'text'],
            ],
        ],
    ],
], false);
$versionService->publishDraftVersion($dynamicForm, $marketing->email);

app(PermissionRegistrar::class)->forgetCachedPermissions();

echo "[E2E Bootstrap] Isolated SQLite database initialized successfully.\n";
