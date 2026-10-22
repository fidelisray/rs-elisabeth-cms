<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;

/**
 * Builder konten yang menutup hard delete massal (I18N-9.3, I-17).
 * Contoh yang ditolak: News::withTrashed()->forceDelete().
 */
class ContentQueryBuilder extends Builder
{
    public function forceDelete()
    {
        HardDeleteGuard::assertAllowed();

        return parent::forceDelete();
    }
}
