<?php

declare(strict_types=1);

namespace App\Services\Export;

use App\Exceptions\ExportAbortedException;
use Illuminate\Support\Facades\RateLimiter;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer as XlsxWriter;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Penulis spreadsheet terpusat untuk ekspor CSV (UTF-8 BOM, RFC 4180)
 * dan Excel native (.xlsx via OpenSpout, bold header, multi-sheet, Pivot-Ready).
 *
 * Menegakkan proteksi seragam lintas modul:
 * - Sanitasi formula injection (/^[=+\-@\t\r]/ diprefix ')
 * - Pembersihan karakter kontrol XML ilegal pada sel XLSX
 * - Guard 0 baris, batas sinkron 10.000 baris, dan rate limit 10x/menit/user
 */
class SpreadsheetExportWriter
{
    public const MAX_SYNC_ROWS = 10_000;

    public const RATE_LIMIT_MAX_ATTEMPTS = 10;

    public const RATE_LIMIT_DECAY_SECONDS = 60;

    public const CHUNK_SIZE = 500;

    /**
     * Validasi batas rate limit, 0 baris, dan hard safety cap sebelum membentuk berkas.
     *
     * @throws ExportAbortedException
     */
    public function enforceGuards(?int $userId, int $rowCount, int $maxRows = self::MAX_SYNC_ROWS): void
    {
        $rateKey = $userId !== null ? 'panel-export:'.$userId : null;

        if ($rateKey !== null && RateLimiter::tooManyAttempts($rateKey, self::RATE_LIMIT_MAX_ATTEMPTS)) {
            throw ExportAbortedException::rateLimited(RateLimiter::availableIn($rateKey));
        }

        if ($rowCount <= 0) {
            throw ExportAbortedException::empty();
        }

        if ($rowCount > $maxRows) {
            throw ExportAbortedException::limitExceeded($rowCount, $maxRows);
        }

        if ($rateKey !== null) {
            RateLimiter::hit($rateKey, self::RATE_LIMIT_DECAY_SECONDS);
        }
    }

    /**
     * Stream berkas CSV dengan UTF-8 BOM agar karakter UTF-8 terbaca benar di Excel.
     *
     * @param  list<string>  $headers
     * @param  iterable<int, list<mixed>>  $rows
     */
    public function streamCsv(string $filename, array $headers, iterable $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($headers, $rows): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            // UTF-8 BOM agar Excel langsung mengenali encoding UTF-8.
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, array_map(fn (string $header): string => (string) $this->sanitizeCell($header), $headers));

            foreach ($rows as $row) {
                fputcsv($handle, array_map($this->sanitizeCell(...), $row));
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * Bangun dan unduh berkas Excel (.xlsx) native menggunakan OpenSpout (mendukung 1 atau multi-sheet).
     *
     * @param  list<array{name: string, headers: list<string>, rows: iterable<int, list<mixed>>}>  $sheets
     */
    public function downloadXlsx(string $filename, array $sheets): BinaryFileResponse
    {
        $tmpFile = tempnam(sys_get_temp_dir(), 'rse_export_xlsx_');
        $xlsxPath = $tmpFile.'.xlsx';
        @unlink($tmpFile);

        $writer = new XlsxWriter;
        $headerStyle = (new Style)->setFontBold();

        try {
            $writer->openToFile($xlsxPath);

            foreach (array_values($sheets) as $index => $sheetDef) {
                $sheet = $index === 0
                    ? $writer->getCurrentSheet()
                    : $writer->addNewSheetAndMakeItCurrent();

                $sheet->setName($this->sanitizeSheetName((string) ($sheetDef['name'] ?? 'Data')));

                $headers = array_map(
                    fn (string $header): string => (string) $this->sanitizeCell($header),
                    $sheetDef['headers'],
                );
                $writer->addRow(Row::fromValues($headers, $headerStyle));

                foreach ($sheetDef['rows'] as $row) {
                    $writer->addRow(Row::fromValues(array_map($this->sanitizeCell(...), $row)));
                }
            }

            $writer->close();
        } catch (Throwable $e) {
            @unlink($xlsxPath);

            throw $e;
        }

        return response()->download($xlsxPath, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ])->deleteFileAfterSend(true);
    }

    /**
     * Mencegah CSV/Spreadsheet formula injection serta karakter kontrol XML tidak valid.
     */
    public function sanitizeCell(mixed $value): int|float|string|bool|null
    {
        if ($value === null || is_int($value) || is_float($value) || is_bool($value)) {
            return $value;
        }

        $str = (string) $value;
        // Hapus karakter kontrol ASCII non-XML (kecuali \t, \n, \r) agar XML XLSX selalu valid.
        $str = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $str);

        if ($str !== '' && preg_match('/^[=+\-@\t\r]/', $str) === 1) {
            return "'".$str;
        }

        return $str;
    }

    /**
     * Normalisasi nama worksheet Excel (maks 31 karakter, tanpa karakter terlarang : \ / ? * [ ]).
     */
    private function sanitizeSheetName(string $name): string
    {
        $clean = trim((string) preg_replace('/[:\\\\\/?*\[\]]/', ' ', $name));
        if ($clean === '') {
            return 'Data';
        }

        return mb_substr($clean, 0, 31);
    }
}
