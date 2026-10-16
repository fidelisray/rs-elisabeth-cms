<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * K-21 dan MVP-3.2: kontrak entrypoint container.
 * Diuji secara statis karena build image tidak dijalankan di CI test.
 */
class EntrypointScriptTest extends TestCase
{
    private string $script;

    protected function setUp(): void
    {
        parent::setUp();

        $this->script = (string) file_get_contents(dirname(__DIR__, 2).'/docker/entrypoint.sh');
    }

    /**
     * Hapus komentar agar pemeriksaan hanya melihat perintah yang dieksekusi.
     */
    private function commands(): string
    {
        return implode("\n", array_filter(
            array_map('trim', explode("\n", $this->script)),
            fn (string $line) => $line !== '' && ! str_starts_with($line, '#')
        ));
    }

    public function test_entrypoint_does_not_run_migrate_or_seed(): void
    {
        $commands = $this->commands();

        $this->assertStringNotContainsString('artisan migrate', $commands);
        $this->assertStringNotContainsString('artisan db:seed', $commands);
    }

    public function test_non_fpm_commands_bypass_bootstrap_so_one_off_artisan_works(): void
    {
        $this->assertMatchesRegularExpression(
            '/if \[ "\$1" != "php-fpm" \]; then\s+exec "\$@"\s+fi/',
            $this->script
        );
    }

    /**
     * Posisi baris perintah pertama yang cocok (mengabaikan komentar).
     */
    private function commandPosition(string $needle): int|false
    {
        return strpos($this->commands(), $needle);
    }

    public function test_bypass_happens_before_any_bootstrap_step(): void
    {
        $bypass = $this->commandPosition('exec "$@"');
        $firstArtisan = $this->commandPosition('php artisan');

        $this->assertNotFalse($bypass);
        $this->assertLessThan($firstArtisan, $bypass);
    }

    public function test_stale_sentinel_is_removed_before_bootstrap_and_recreated_at_end(): void
    {
        $remove = $this->commandPosition('rm -f /tmp/app_healthy');
        $firstArtisan = $this->commandPosition('php artisan');
        $touch = $this->commandPosition('touch /tmp/app_healthy');

        $this->assertNotFalse($remove, 'Sentinel lama harus dihapus.');
        $this->assertNotFalse($touch);
        $this->assertLessThan($firstArtisan, $remove, 'Sentinel harus dihapus sebelum bootstrap berjalan.');
        $this->assertGreaterThan($firstArtisan, $touch, 'Sentinel hanya dibuat setelah bootstrap selesai.');
    }

    public function test_script_stops_on_first_error(): void
    {
        $this->assertStringContainsString("set -e\n", $this->script);
    }
}
