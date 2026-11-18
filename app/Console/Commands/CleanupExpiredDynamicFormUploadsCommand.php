<?php

namespace App\Console\Commands;

use App\Models\DynamicFormUpload;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

class CleanupExpiredDynamicFormUploadsCommand extends Command
{
    protected $signature = 'app:dynamic-forms:cleanup-uploads';

    protected $description = 'Hapus berkas unggahan sementara form dinamis yang sudah kedaluwarsa dan belum terikat ke submission.';

    public function handle(): int
    {
        $deletedCount = 0;

        DynamicFormUpload::query()
            ->whereNull('submission_id')
            ->where('expires_at', '<', now())
            ->orderBy('id')
            ->chunkById(100, function ($uploads) use (&$deletedCount): void {
                foreach ($uploads as $upload) {
                    try {
                        if ($upload->storage_path !== '' && Storage::disk($upload->disk)->exists($upload->storage_path)) {
                            Storage::disk($upload->disk)->delete($upload->storage_path);
                        }
                    } catch (Throwable $exception) {
                        report($exception);
                    }

                    $upload->delete();
                    $deletedCount++;
                }
            });

        $this->info("Membersihkan {$deletedCount} unggahan kedaluwarsa.");

        return self::SUCCESS;
    }
}
