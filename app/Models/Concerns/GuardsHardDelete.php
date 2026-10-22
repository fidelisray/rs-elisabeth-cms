<?php

namespace App\Models\Concerns;

use App\Support\ContentQueryBuilder;
use App\Support\HardDeleteGuard;

/**
 * Menutup jalur hard delete konten (I18N-9.3, I-17).
 *
 * Dipakai bersama SoftDeletes. Method SoftDeletes::forceDelete dan forceDeleteQuietly
 * digantikan (insteadof) agar pemeriksaan token berjalan sebelum flag forceDeleting diset.
 * Hanya App\Actions\HardDeleteContent yang memasang token lewat HardDeleteGuard::allow().
 * Soft delete (delete) tidak terpengaruh.
 *
 * Catatan: parent:: di dalam trait merujuk ke Model, bukan SoftDeletes, sehingga logika
 * forceDeleting dan event forceDeleting/forceDeleted ditiru di sini.
 */
trait GuardsHardDelete
{
    public function forceDelete()
    {
        HardDeleteGuard::assertAllowed();

        if ($this->fireModelEvent('forceDeleting') === false) {
            return false;
        }

        $this->forceDeleting = true;

        return tap($this->delete(), function ($deleted) {
            $this->forceDeleting = false;

            if ($deleted) {
                $this->fireModelEvent('forceDeleted', false);
            }
        });
    }

    public function forceDeleteQuietly()
    {
        return static::withoutEvents(fn () => $this->forceDelete());
    }

    public function newEloquentBuilder($query)
    {
        return new ContentQueryBuilder($query);
    }
}
