<?php

namespace App\Jobs;

use App\Services\WebFrontendWebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

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
    ) {
        $this->afterCommit = true;
    }

    /**
     * Execute the job.
     */
    public function handle(WebFrontendWebhookService $service): void
    {
        $service->dispatch($this->module, $this->event, $this->slug);
    }
}
