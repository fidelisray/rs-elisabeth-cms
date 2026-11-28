<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/**
 * Exception domain untuk menghentikan proses ekspor secara aman dan terkendali
 * (0 baris cocok, melebihi batas maksimal ekspor sinkron, atau terkena rate limit).
 */
class ExportAbortedException extends RuntimeException
{
    public const REASON_EMPTY = 'empty';

    public const REASON_LIMIT_EXCEEDED = 'limit_exceeded';

    public const REASON_RATE_LIMITED = 'rate_limited';

    public function __construct(
        public readonly string $reason,
        public readonly string $title,
        string $body,
    ) {
        parent::__construct($body);
    }

    public static function empty(): self
    {
        return new self(
            reason: self::REASON_EMPTY,
            title: __('Tidak ada data untuk diekspor'),
            body: __('Tidak ada data yang cocok dengan filter aktif saat ini. Sesuaikan filter atau rentang tanggal lalu coba kembali.'),
        );
    }

    public static function limitExceeded(int $rowCount, int $maxRows): self
    {
        return new self(
            reason: self::REASON_LIMIT_EXCEEDED,
            title: __('Jumlah data melebihi batas ekspor'),
            body: __('Terdapat :count baris yang cocok (batas maksimal :max baris per ekspor sinkron). Persempit filter rentang tanggal atau layanan terlebih dahulu.', [
                'count' => number_format($rowCount),
                'max' => number_format($maxRows),
            ]),
        );
    }

    public static function rateLimited(int $seconds): self
    {
        return new self(
            reason: self::REASON_RATE_LIMITED,
            title: __('Batas permintaan ekspor tercapai'),
            body: __('Silakan tunggu :seconds detik sebelum menjalankan ekspor data berikutnya.', [
                'seconds' => $seconds,
            ]),
        );
    }
}
