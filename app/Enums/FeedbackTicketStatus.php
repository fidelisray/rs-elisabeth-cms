<?php

namespace App\Enums;

enum FeedbackTicketStatus: string
{
    case Baru = 'baru';
    case Diproses = 'diproses';
    case Selesai = 'selesai';

    public function label(): string
    {
        return match ($this) {
            self::Baru => 'Baru',
            self::Diproses => 'Diproses',
            self::Selesai => 'Selesai',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::Baru->value => self::Baru->label(),
            self::Diproses->value => self::Diproses->label(),
            self::Selesai->value => self::Selesai->label(),
        ];
    }
}
