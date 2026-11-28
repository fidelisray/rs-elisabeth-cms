<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Support\WidgetPermissionAliases;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

class PermissionSeeder extends Seeder
{
    /**
     * Daftar aksi permission standar yang di-generate oleh Filament Shield.
     */
    protected const RESOURCE_ACTIONS = [
        'ViewAny',
        'View',
        'Create',
        'Update',
        'Delete',
        'DeleteAny',
        'Restore',
        'ForceDelete',
        'ForceDeleteAny',
        'RestoreAny',
        'Replicate',
        'Reorder',
    ];

    /**
     * Seluruh subject resource yang membutuhkan permission lengkap.
     */
    protected const ALL_SUBJECTS = [
        'Article',
        'News',
        'Promotion',
        'BannerPromotion',
        'FacilityService',
        'RoomFacility',
        'Doctor',
        'User',
        'Role',
        'OnlineLibrary',
        'DynamicForm',
    ];

    /**
     * Nama widget yang dipakai untuk permission widget.
     */
    protected const WIDGET_SUBJECTS = [
        'FeedbackSectionWidget',
        'QuestionnaireSectionWidget',
        'DynamicFeedbackOverviewWidget',
    ];

    protected const PATIENT_SUBJECTS = [
        'Feedback',
        'QuestionnaireResponse',
        'DynamicFormSubmission',
    ];

    /**
     * Run the database seeds.
     *
     * Seeder ini HANYA mengisi tabel permissions secara idempoten.
     * Tidak membuat akun maupun role apa pun. Jika role super_admin sudah ada,
     * permission baru akan ditambahkan secara additive tanpa mencabut yang ada.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        app(WidgetPermissionAliases::class)->reconcile();

        $this->seedPermissions();
        $this->syncPermissionsToExistingSuperAdmin();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function seedPermissions(): void
    {
        foreach (self::ALL_SUBJECTS as $subject) {
            foreach (self::RESOURCE_ACTIONS as $action) {
                Permission::findOrCreate("{$action}:{$subject}", 'web');
            }
        }

        foreach (self::WIDGET_SUBJECTS as $subject) {
            Permission::findOrCreate("View:{$subject}", 'web');
        }

        foreach (self::PATIENT_SUBJECTS as $subject) {
            foreach (['ViewAny', 'View'] as $action) {
                Permission::findOrCreate("{$action}:{$subject}", 'web');
            }
        }

        Permission::findOrCreate('Update:Feedback', 'web');
        Permission::findOrCreate('Update:DynamicFormSubmission', 'web');
        Permission::findOrCreate('View:SystemMonitor', 'web');
        Permission::findOrCreate('Trigger:WebCacheInvalidation', 'web');
        Permission::findOrCreate('Export:Feedback', 'web');
        Permission::findOrCreate('Export:QuestionnaireResponse', 'web');
        Permission::findOrCreate('Export:DynamicFormSubmission', 'web');
    }

    /**
     * Jika role super_admin sudah dibuat (lewat app:super-admin:init),
     * pastikan permission baru dari deploy berikutnya otomatis ikut diberikan
     * secara additive tanpa pernah membuat role baru atau mencabut permission.
     */
    protected function syncPermissionsToExistingSuperAdmin(): void
    {
        $superAdminRole = Role::where('name', Role::SUPER_ADMIN)
            ->where('guard_name', 'web')
            ->first();

        if (! $superAdminRole) {
            return;
        }

        $superAdminRole->givePermissionTo(
            Permission::where('guard_name', 'web')->get()
        );
    }
}
