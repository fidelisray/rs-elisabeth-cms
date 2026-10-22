<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Support\HardDeleteGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Satu-satunya jalur hard delete konten (I18N-9.4, I-8, I-16, I-17).
 *
 * - Hanya super_admin yang berhasil. Pemeriksaan role dilakukan di sini, bukan hanya di UI.
 * - Ditolak di console (lihat HardDeleteGuard).
 * - Audit content.force_deleted dicatat dalam transaksi yang sama dengan delete.
 * - File media dihapus setelah commit, agar kegagalan database tidak menghilangkan file.
 */
class HardDeleteContent
{
    /**
     * Kolom file media yang dihapus bersama record.
     */
    public const MEDIA_COLUMNS = ['image_path'];

    public function handle(Model $record): bool
    {
        $user = auth()->user();

        if (! $user || ! $user->hasRole('super_admin')) {
            abort(403, 'Hanya super_admin yang dapat menghapus konten secara permanen.');
        }

        $mediaPaths = $this->mediaPaths($record);
        $disk = config('filesystems.media_disk');

        return HardDeleteGuard::allow(function () use ($record, $mediaPaths, $disk): bool {
            return DB::transaction(function () use ($record, $mediaPaths, $disk): bool {
                AuditLog::record('content.force_deleted', $record, [
                    'module' => $record->getTable(),
                    'slug_id' => $record->getTranslation('slug', 'id', false),
                    'slug_en' => $record->getTranslation('slug', 'en', false),
                    'source' => 'panel',
                ]);

                $deleted = (bool) $record->forceDelete();

                if ($deleted && $mediaPaths !== []) {
                    DB::afterCommit(function () use ($mediaPaths, $disk): void {
                        // Exception di afterCommit akan dilempar ke pemanggil setelah commit,
                        // sehingga data sudah terhapus tetapi request gagal. Ditangani di sini.
                        foreach ($mediaPaths as $path) {
                            try {
                                Storage::disk($disk)->delete($path);
                            } catch (Throwable $exception) {
                                report($exception);
                            }
                        }
                    });
                }

                return $deleted;
            });
        });
    }

    /**
     * @return list<string>
     */
    private function mediaPaths(Model $record): array
    {
        return collect(self::MEDIA_COLUMNS)
            ->map(fn (string $column) => $record->getRawOriginal($column))
            ->filter(fn ($path) => is_string($path) && $path !== '')
            ->values()
            ->all();
    }
}
