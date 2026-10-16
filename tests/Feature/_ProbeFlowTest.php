<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class _ProbeFlowTest extends TestCase
{
    use RefreshDatabase;

    private function snapshotFor(string $url): ?string
    {
        $html = $this->get($url)->getContent();
        if (preg_match('/wire:snapshot="([^"]+)"/', $html, $m)) {
            return htmlspecialchars_decode($m[1], ENT_QUOTES);
        }

        return null;
    }

    public function test_probe(): void
    {
        $this->seed(DatabaseSeeder::class);
        $user = User::where('email', 'marketing@rs-elisabeth.com')->first();

        // 1) Ambil snapshot ASLI ListNews saat user BELUM wajib ganti password.
        $user->update(['must_change_password' => false]);
        $this->actingAs($user->fresh());
        $snapshot = $this->snapshotFor('/admin/news');
        fwrite(STDERR, "\nPROBE_SNAPSHOT_FOUND=".($snapshot ? 'ya' : 'tidak')."\n");

        // 2) Sekarang wajibkan ganti password, lalu kirim update Livewire untuk komponen ListNews.
        $user->update(['must_change_password' => true]);
        $this->actingAs($user->fresh());

        $res = $this->withHeaders(['X-Livewire' => 'true', 'Accept' => 'application/json'])
            ->postJson('/livewire-cefdcb1b/update', [
                'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]],
            ]);
        fwrite(STDERR, "\nPROBE_UPDATE_STATUS=".$res->getStatusCode()."\n");
        fwrite(STDERR, "\nPROBE_UPDATE_BODY=".substr(str_replace("\n", ' ', $res->getContent()), 0, 200)."\n");
        $this->assertTrue(true);
    }
}
