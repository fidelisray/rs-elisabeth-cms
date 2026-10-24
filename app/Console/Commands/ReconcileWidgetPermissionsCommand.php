<?php

namespace App\Console\Commands;

use App\Support\WidgetPermissionAliases;
use Illuminate\Console\Command;

class ReconcileWidgetPermissionsCommand extends Command
{
    protected $signature = 'app:permissions:reconcile-widgets';

    protected $description = 'Pindahkan grant nama widget lama ke nama baru tanpa mereset role atau akun';

    public function handle(WidgetPermissionAliases $aliases): int
    {
        $aliases->reconcile();
        $this->info('Rekonsiliasi permission widget selesai. Tidak ada default role yang direset.');

        return self::SUCCESS;
    }
}
