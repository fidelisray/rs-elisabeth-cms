<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

final class WidgetPermissionAliases
{
    public function reconcile(): void
    {
        DB::transaction(function (): void {
            foreach ([
                'View:FeedbackStatsWidget' => 'View:FeedbackSectionWidget',
                'View:LatestFeedbackWidget' => 'View:QuestionnaireSectionWidget',
            ] as $oldName => $newName) {
                $old = Permission::where('name', $oldName)->where('guard_name', 'web')->first();
                if (! $old) {
                    continue;
                }
                $new = Permission::where('name', $newName)->where('guard_name', 'web')->first();
                if (! $new) {
                    // Rename in place: seluruh relasi tetap memakai permission ID yang sama.
                    $old->update(['name' => $newName]);

                    continue;
                }
                foreach ($old->roles()->get() as $role) {
                    $role->givePermissionTo($new);
                }
                foreach ($old->users()->get() as $user) {
                    $user->givePermissionTo($new);
                }
                // Grant lama sudah dipindah; hapus alias agar reseed tidak mengembalikan grant
                // baru yang kelak sengaja dicabut admin.
                $old->delete();
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
