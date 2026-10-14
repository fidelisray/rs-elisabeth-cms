<?php

namespace App\Console\Commands;

use App\Services\WebFrontendWebhookService;
use Illuminate\Console\Command;

class InvalidateWebFrontendCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'webhook:cache-invalidate
                            {module=all : Module to invalidate (news, articles, promotions, facilities, room_facilities, banner_promotions, all)}
                            {--event=content.updated : Event type (content.updated, content.created, content.deleted)}
                            {--slug= : Specific entity slug (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch a cache invalidation webhook signal to the Web Frontend (Zero-Delay Cache Invalidation)';

    /**
     * Execute the console command.
     */
    public function handle(WebFrontendWebhookService $service): int
    {
        $module = $this->argument('module');
        $event = $this->option('event');
        $slug = $this->option('slug');

        $this->info('Mengirim sinyal Webhook Cache Invalidation ke Web Frontend...');
        $this->table(
            ['Parameter', 'Nilai'],
            [
                ['URL', config('web_frontend.webhook_url') ?: '<BELUM DIKONFIGURASI>'],
                ['Consumer ID', config('web_frontend.cons_id')],
                ['Module', $module],
                ['Event', $event],
                ['Slug', $slug ?: '-'],
            ]
        );

        try {
            $success = $service->dispatch($module, $event, $slug);

            if ($success) {
                $this->info('✓ Berhasil! Web Frontend menerima sinyal invalidasi dan merespon 200 OK.');

                return self::SUCCESS;
            }

            $this->error('✗ Webhook tidak dikirim (URL atau Secret Key belum dikonfigurasi).');

            return self::FAILURE;
        } catch (\Throwable $e) {
            $this->error('✗ Gagal mengirim webhook ke Web Frontend: '.$e->getMessage());

            return self::FAILURE;
        }
    }
}
