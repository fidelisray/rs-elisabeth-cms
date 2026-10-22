<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Hard delete konten hanya boleh dilakukan melalui App\Actions\HardDeleteContent
 * oleh super_admin dari request web (I18N-9.3, I-17).
 */
class HardDeleteForbiddenException extends RuntimeException
{
    public static function bypassed(): self
    {
        return new self('Hard delete konten hanya dapat dilakukan oleh super_admin melalui panel web.');
    }
}
