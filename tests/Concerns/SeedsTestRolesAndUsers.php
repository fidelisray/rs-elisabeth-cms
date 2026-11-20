<?php

namespace Tests\Concerns;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

/**
 * Helper khusus testing untuk membuat fixture role & akun staf
 * setelah pembuatan akun/role dihapus dari database seeder produksi.
 */
trait SeedsTestRolesAndUsers
{
    /**
     * @var list<string>
     */
    private const TEST_CONTENT_SUBJECTS = [
        'Article',
        'News',
        'Promotion',
        'BannerPromotion',
        'FacilityService',
        'RoomFacility',
        'OnlineLibrary',
        'DynamicForm',
    ];

    /**
     * @var list<string>
     */
    private const TEST_REORDERABLE_SUBJECTS = [
        'BannerPromotion',
        'FacilityService',
        'RoomFacility',
    ];

    /**
     * @var list<string>
     */
    private const TEST_WIDGET_SUBJECTS = [
        'FeedbackSectionWidget',
        'QuestionnaireSectionWidget',
        'DynamicFeedbackOverviewWidget',
    ];

    /**
     * @var list<string>
     */
    private const TEST_PATIENT_SUBJECTS = [
        'Feedback',
        'QuestionnaireResponse',
        'DynamicFormSubmission',
    ];

    protected function seedDefaultTestUsersAndRoles(): void
    {
        $this->seed(PermissionSeeder::class);

        $this->createTestSuperAdminRole();
        $marketingRole = $this->createTestMarketingRole();
        $staffItRole = $this->createTestStaffItRole();

        $marketing = User::firstOrCreate(
            ['email' => 'marketing@rs-elisabeth.com'],
            [
                'name' => 'Staf Marketing',
                'password' => Hash::make('123456'),
                'must_change_password' => true,
            ]
        );
        if (! $marketing->hasRole($marketingRole)) {
            $marketing->assignRole($marketingRole);
        }

        $staffIt = User::firstOrCreate(
            ['email' => 'it@rs-elisabeth.com'],
            [
                'name' => 'Staff IT',
                'password' => Hash::make('123456'),
                'must_change_password' => true,
            ]
        );
        if (! $staffIt->hasRole($staffItRole)) {
            $staffIt->assignRole($staffItRole);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    protected function createTestSuperAdminRole(): Role
    {
        $role = Role::findOrCreate(Role::SUPER_ADMIN, 'web');
        $role->givePermissionTo(Permission::where('guard_name', 'web')->get());

        return $role;
    }

    protected function createTestMarketingRole(): Role
    {
        $allowed = ['ViewAny', 'View', 'Create', 'Update', 'Delete', 'DeleteAny'];
        $names = [];

        foreach (self::TEST_CONTENT_SUBJECTS as $subject) {
            foreach ($allowed as $action) {
                $names[] = "{$action}:{$subject}";
            }
        }

        $names[] = 'ViewAny:Doctor';
        $names[] = 'View:Doctor';
        $names[] = 'Update:Doctor';

        foreach (self::TEST_PATIENT_SUBJECTS as $subject) {
            $names[] = "ViewAny:{$subject}";
            $names[] = "View:{$subject}";
        }

        foreach (self::TEST_REORDERABLE_SUBJECTS as $subject) {
            $names[] = "Reorder:{$subject}";
        }

        foreach (self::TEST_WIDGET_SUBJECTS as $subject) {
            $names[] = "View:{$subject}";
        }

        $role = Role::findOrCreate('marketing', 'web');
        if ($role->wasRecentlyCreated) {
            $role->syncPermissions(Permission::whereIn('name', $names)->get());
        }

        return $role;
    }

    protected function createTestStaffItRole(): Role
    {
        $names = [
            'ViewAny:Doctor',
            'View:Doctor',
        ];

        foreach (self::TEST_CONTENT_SUBJECTS as $subject) {
            $names[] = "ViewAny:{$subject}";
            $names[] = "View:{$subject}";
        }

        foreach (self::TEST_PATIENT_SUBJECTS as $subject) {
            $names[] = "ViewAny:{$subject}";
            $names[] = "View:{$subject}";
        }

        foreach (self::TEST_WIDGET_SUBJECTS as $subject) {
            $names[] = "View:{$subject}";
        }

        $role = Role::findOrCreate('Staff IT', 'web');
        if ($role->wasRecentlyCreated) {
            $role->syncPermissions(Permission::whereIn('name', $names)->get());
        }

        return $role;
    }
}
