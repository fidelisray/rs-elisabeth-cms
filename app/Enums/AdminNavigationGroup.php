<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\Collapsible;
use Filament\Support\Contracts\HasLabel;

/**
 * Grup navigasi Panel Admin Filament yang dievaluasi secara lazy per request
 * agar label sidebar ikut berubah dinamis saat user mengganti bahasa (ID <-> EN).
 */
enum AdminNavigationGroup: string implements Collapsible, HasLabel
{
    case MarketingEditorial = 'marketing_editorial';
    case ServicesRooms = 'services_rooms';
    case DoctorsLibrary = 'doctors_library';
    case PatientFeedback = 'patient_feedback';
    case AccessControl = 'access_control';

    public function getLabel(): string
    {
        return match ($this) {
            self::MarketingEditorial => __('Pemasaran & Editorial'),
            self::ServicesRooms => __('Layanan & Kamar'),
            self::DoctorsLibrary => __('Dokter & Perpustakaan'),
            self::PatientFeedback => __('Masukan Pasien'),
            self::AccessControl => __('Kontrol Akses'),
        };
    }

    public function isCollapsible(): bool
    {
        return true;
    }

    public function isCollapsed(): bool
    {
        return false;
    }
}
