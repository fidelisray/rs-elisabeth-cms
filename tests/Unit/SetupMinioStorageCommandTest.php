<?php

namespace Tests\Unit;

use App\Console\Commands\SetupMinioStorageCommand;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\TestCase;

class SetupMinioStorageCommandTest extends TestCase
{
    public function test_public_read_policy_whitelists_only_public_module_prefixes_and_excludes_dynamic_forms(): void
    {
        $policy = SetupMinioStorageCommand::buildPublicReadPolicy('rse-media-bucket');

        $this->assertSame('2012-10-17', $policy['Version']);
        $this->assertCount(1, $policy['Statement']);

        $statement = $policy['Statement'][0];
        $this->assertSame('Allow', $statement['Effect']);
        $this->assertSame('*', $statement['Principal']);
        $this->assertSame('s3:GetObject', $statement['Action']);

        $resources = $statement['Resource'];
        $this->assertIsArray($resources);
        $this->assertNotContains('arn:aws:s3:::rse-media-bucket/*', $resources);
        $this->assertNotContains('arn:aws:s3:::rse-media-bucket/dynamic-forms/*', $resources);

        foreach (SetupMinioStorageCommand::PUBLIC_MEDIA_PREFIXES as $prefix) {
            $this->assertContains("arn:aws:s3:::rse-media-bucket/{$prefix}/*", $resources);
        }
        $this->assertCount(count(SetupMinioStorageCommand::PUBLIC_MEDIA_PREFIXES), $resources);
    }

    public function test_every_filament_public_media_directory_is_registered_in_public_media_prefixes(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator(app_path('Filament'), FilesystemIterator::SKIP_DOTS)
        );

        $foundDirectories = [];

        foreach ($iterator as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $source = (string) file_get_contents($file->getPathname());
            if (preg_match_all("/->directory\\(\\s*'([^']+)'\\s*\\)/", $source, $matches)) {
                foreach ($matches[1] as $dir) {
                    $topLevelPrefix = explode('/', trim($dir, '/'))[0];
                    if ($topLevelPrefix !== '') {
                        $foundDirectories[$topLevelPrefix] = $file->getPathname();
                    }
                }
            }
        }

        $this->assertNotEmpty($foundDirectories);

        foreach (array_keys($foundDirectories) as $directoryPrefix) {
            $this->assertContains(
                $directoryPrefix,
                SetupMinioStorageCommand::PUBLIC_MEDIA_PREFIXES,
                "Folder media publik [{$directoryPrefix}] pada Filament belum didaftarkan di SetupMinioStorageCommand::PUBLIC_MEDIA_PREFIXES."
            );
        }
    }
}
