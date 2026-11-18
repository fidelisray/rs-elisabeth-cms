<?php

namespace App\Enums;

enum DynamicFormStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draf',
            self::Published => 'Dipublikasikan',
            self::Closed => 'Ditutup',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Draft->value => self::Draft->label(),
            self::Published->value => self::Published->label(),
            self::Closed->value => self::Closed->label(),
        ];
    }
}
