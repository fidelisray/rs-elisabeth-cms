<?php

namespace App\Filament\Support;

use App\Actions\HardDeleteContent;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Throwable;

/**
 * Aksi pemulihan dan hard delete untuk tabel konten (I18N-9.4, I18N-9.5).
 * Semua aksi dan filter hanya tampil untuk super_admin.
 */
final class ContentTrashActions
{
    public static function isSuperAdmin(): bool
    {
        return Auth::user()?->hasRole('super_admin') ?? false;
    }

    /**
     * visible() menggantikan default Filament yang hanya menampilkan aksi untuk record terhapus,
     * sehingga kondisi trashed() harus dipertahankan secara eksplisit.
     */
    public static function forceDeleteAction(): ForceDeleteAction
    {
        return ForceDeleteAction::make()
            ->using(fn (Model $record): bool => app(HardDeleteContent::class)->handle($record))
            ->visible(fn (Model $record): bool => self::isSuperAdmin() && self::isTrashed($record));
    }

    /**
     * Setiap record dihapus lewat HardDeleteContent agar audit, transaksi, dan file media tetap berjalan.
     * Tidak memakai fetchSelectedRecords(false), karena jalur query massal akan melewati semua itu.
     */
    public static function forceDeleteBulkAction(): ForceDeleteBulkAction
    {
        return ForceDeleteBulkAction::make()
            ->using(function (ForceDeleteBulkAction $action, Collection $records): void {
                $handler = app(HardDeleteContent::class);
                $failed = 0;

                foreach ($records as $record) {
                    try {
                        $handler->handle($record) || $failed++;
                    } catch (Throwable $exception) {
                        $failed++;
                        report($exception);
                    }
                }

                $failed === 0
                    ? $action->reportBulkProcessingSuccessfulRecordsCount($records->count())
                    : $action->reportBulkProcessingFailure();
            })
            ->visible(fn (): bool => self::isSuperAdmin());
    }

    public static function restoreAction(): RestoreAction
    {
        return RestoreAction::make()
            ->visible(fn (Model $record): bool => self::isSuperAdmin() && self::isTrashed($record));
    }

    private static function isTrashed(Model $record): bool
    {
        return method_exists($record, 'trashed') && $record->trashed();
    }

    public static function restoreBulkAction(): RestoreBulkAction
    {
        return RestoreBulkAction::make()
            ->visible(fn (): bool => self::isSuperAdmin());
    }

    public static function trashedFilter(): TrashedFilter
    {
        return TrashedFilter::make()
            ->visible(fn (): bool => self::isSuperAdmin());
    }
}
