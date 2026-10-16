<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class GenerateCmsSecretCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'cms:generate-secret {--show : Hanya tampilkan key tanpa menyimpannya ke .env}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate a secure, random secret key for CMS API authentication (HMAC)';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Generate cryptographically secure key (64 characters string)
        $key = Str::random(64);

        if ($this->option('show')) {
            $this->line('<comment>Generated CMS Secret Key:</comment> '.$key);

            return Command::SUCCESS;
        }

        // 2. Set the key in the .env file
        if (! $this->setEnvKey($key)) {
            $this->error('Failed to update the .env file. Please update it manually.');
            $this->line('CMS_API_SECRET_WEB='.$key);

            return Command::FAILURE;
        }

        $this->info('CMS API Secret Key successfully generated and set in your .env file!');
        $this->line('<comment>New Key:</comment> '.$key);

        return Command::SUCCESS;
    }

    /**
     * Tulis secret key baru ke dalam file .env.
     */
    protected function setEnvKey(string $key): bool
    {
        /** @var Application $app */
        $app = $this->laravel;
        $envPath = $app->environmentFilePath();

        if (! File::exists($envPath)) {
            return false;
        }

        $envContent = File::get($envPath);

        // Jika CMS_API_SECRET_WEB sudah ada, timpa nilainya
        if (str_contains($envContent, 'CMS_API_SECRET_WEB=')) {
            $envContent = preg_replace(
                '/^CMS_API_SECRET_WEB=.*$/m',
                'CMS_API_SECRET_WEB='.$key,
                $envContent
            );
        } else {
            // Jika belum ada, tambahkan di baris paling bawah
            $envContent .= PHP_EOL.'CMS_API_SECRET_WEB='.$key.PHP_EOL;
        }

        return File::put($envPath, $envContent) !== false;
    }
}
