<?php

namespace App\Jobs;

use App\Services\WebFrontendWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendCacheInvalidationWebhookJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Jumlah maksimum percobaan jika eksekusi gagal.
     */
    public int $tries = 3;

    /**
     * Jeda waktu sebelum percobaan ulang (detik).
     */
    public int $backoff = 5;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $module,
        public string $event = 'content.updated',
        public ?string $slug = null
    ) {}

    /**
     * Execute the job.
     */
    public function handle(WebFrontendWebhookService $service): void
    {
        $service->dispatch($this->module, $this->event, $this->slug);
    }

    /**
     * Tangani kegagalan final (setelah seluruh percobaan retry habis).
     * Memberi jejak audit yang jelas di log & failed_jobs.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('Web Frontend Webhook - Job gagal setelah retry habis.', [
            'module' => $this->module,
            'event' => $this->event,
            'slug' => $this->slug,
            'exception' => $exception->getMessage(),
        ]);
    }
}
