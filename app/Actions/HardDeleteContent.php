<?php

namespace App\Actions;

use App\Models\AuditLog;
use App\Models\DynamicForm;
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
        $privateUploads = $record instanceof DynamicForm
            ? $record->uploads()->get(['disk', 'storage_path'])->map(fn ($u) => ['disk' => $u->disk, 'path' => $u->storage_path])->all()
            : [];

        return HardDeleteGuard::allow(function () use ($record, $mediaPaths, $disk, $privateUploads): bool {
            return DB::transaction(function () use ($record, $mediaPaths, $disk, $privateUploads): bool {
                $hasTranslatedSlug = method_exists($record, 'isTranslatableAttribute') && $record->isTranslatableAttribute('slug');

                AuditLog::record('content.force_deleted', $record, [
                    'module' => $record->getTable(),
                    'slug_id' => $hasTranslatedSlug ? $record->getTranslation('slug', 'id', false) : null,
                    'slug_en' => $hasTranslatedSlug ? $record->getTranslation('slug', 'en', false) : null,
                    'slug' => $hasTranslatedSlug ? null : ($record->getAttribute('slug') ?? null),
                    'source' => 'panel',
                ]);

                // Hapus submissions lebih dulu agar FK restrictOnDelete pada dynamic_form_version_id
                // tidak memblokir penghapusan permanen form beserta versinya.
                if ($record instanceof DynamicForm) {
                    DB::table('dynamic_form_submissions')->where('dynamic_form_id', $record->id)->delete();
                }

                $deleted = (bool) $record->forceDelete();

                if ($deleted && ($mediaPaths !== [] || $privateUploads !== [])) {
                    DB::afterCommit(function () use ($mediaPaths, $disk, $privateUploads): void {
                        // Exception di afterCommit akan dilempar ke pemanggil setelah commit,
                        // sehingga data sudah terhapus tetapi request gagal. Ditangani di sini.
                        foreach ($mediaPaths as $path) {
                            try {
                                Storage::disk($disk)->delete($path);
                            } catch (Throwable $exception) {
                                report($exception);
                            }
                        }

                        foreach ($privateUploads as $upload) {
                            try {
                                if (! empty($upload['path'])) {
                                    Storage::disk($upload['disk'])->delete($upload['path']);
                                }
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
