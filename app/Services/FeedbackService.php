<?php

namespace App\Services;

use App\Models\Feedback;
use App\Models\QuestionnaireResponse;
use Illuminate\Support\Collection;

/**
 * Sumber data dashboard untuk kritik/saran pasien dan kuesioner kepuasan.
 *
 * Seluruh data berasal dari database CMS (tabel feedbacks dan questionnaire_responses).
 * Tidak ada data dummy.
 */
class FeedbackService
{
    /**
     * Ringkasan feedback untuk dashboard.
     *
     * @return array{total: int, baru: int, diproses: int, selesai: int}
     */
    public function getFeedbackSummary(): array
    {
        $counts = Feedback::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        return [
            'total' => (int) $counts->sum(),
            'baru' => (int) ($counts['baru'] ?? 0),
            'diproses' => (int) ($counts['diproses'] ?? 0),
            'selesai' => (int) ($counts['selesai'] ?? 0),
        ];
    }

    /**
     * Daftar kritik/saran terbaru. Nama dan nomor telepon disamarkan di dashboard.
     *
     * @return Collection<int, Feedback>
     */
    public function getLatestFeedback(int $limit = 10): Collection
    {
        return Feedback::query()
            ->latest('submitted_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Ringkasan kuesioner per jenis: jumlah pengisian dan jumlah per kelompok usia.
     *
     * @return array{total: int, by_age: array<string, int>}
     */
    public function getQuestionnaireSummary(string $type): array
    {
        $base = QuestionnaireResponse::query()->where('questionnaire_type', $type);

        $byAge = (clone $base)
            ->selectRaw('age_group, count(*) as total')
            ->groupBy('age_group')
            ->pluck('total', 'age_group')
            ->map(fn ($v) => (int) $v)
            ->all();

        return [
            'total' => (clone $base)->count(),
            'by_age' => $byAge,
        ];
    }

    /**
     * Pengisian kuesioner terbaru untuk satu jenis.
     *
     * @return Collection<int, QuestionnaireResponse>
     */
    public function getLatestQuestionnaires(string $type, int $limit = 10): Collection
    {
        return QuestionnaireResponse::query()
            ->where('questionnaire_type', $type)
            ->latest('submitted_at')
            ->limit($limit)
            ->get();
    }

    /**
     * Persentase jawaban positif pada seluruh skala (dua tingkat tertinggi).
     * Ini bukan skor rata-rata bintang.
     */
    public function getSatisfactionPercent(string $type): int
    {
        $responses = QuestionnaireResponse::query()
            ->where('questionnaire_type', $type)
            ->select('ratings')->cursor();

        $positive = ['Puas', 'Sangat Puas', 'Setuju', 'Sangat Setuju', 'Mudah', 'Sangat Mudah', 'Memadai', 'Sangat Memadai'];
        $total = 0;
        $good = 0;

        foreach ($responses as $response) {
            foreach ((array) $response->ratings as $scale) {
                $total++;
                if (in_array($scale, $positive, true)) {
                    $good++;
                }
            }
        }

        return $total === 0 ? 0 : (int) round($good / $total * 100);
    }
}
