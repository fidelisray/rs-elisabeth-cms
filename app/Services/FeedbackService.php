<?php

namespace App\Services;

use App\Models\Feedback;
use App\Models\QuestionnaireResponse;
use App\Support\QuestionnaireDefinition;
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
     * Daftar skala jawaban yang tergolong positif (dua tingkat tertinggi).
     *
     * @var list<string>
     */
    public const POSITIVE_SCALES = [
        'Puas',
        'Sangat Puas',
        'Setuju',
        'Sangat Setuju',
        'Mudah',
        'Sangat Mudah',
        'Memadai',
        'Sangat Memadai',
    ];

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
     * Ringkasan kuesioner per jenis: jumlah pengisian, kelompok usia, cara bayar, dan jenis kelamin.
     *
     * @return array{total: int, by_age: array<string, int>, by_payment: array<string, int>, by_gender: array<string, int>}
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

        $byPayment = (clone $base)
            ->selectRaw('payment_method, count(*) as total')
            ->groupBy('payment_method')
            ->pluck('total', 'payment_method')
            ->map(fn ($v) => (int) $v)
            ->all();

        $byGender = (clone $base)
            ->selectRaw('gender, count(*) as total')
            ->groupBy('gender')
            ->pluck('total', 'gender')
            ->map(fn ($v) => (int) $v)
            ->all();

        return [
            'total' => (clone $base)->count(),
            'by_age' => $byAge,
            'by_payment' => $byPayment,
            'by_gender' => $byGender,
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

        $total = 0;
        $good = 0;

        foreach ($responses as $response) {
            foreach ((array) $response->ratings as $scale) {
                $total++;
                if (in_array($scale, self::POSITIVE_SCALES, true)) {
                    $good++;
                }
            }
        }

        return $total === 0 ? 0 : (int) round($good / $total * 100);
    }

    /**
     * Ringkasan per aspek pertanyaan untuk satu jenis layanan:
     * mengembalikan hingga 3 aspek tertinggi dan 3 aspek yang paling perlu perhatian.
     *
     * @return array{highest: list<array{code: string, label: string, percent: int, positive: int, total: int}>, lowest: list<array{code: string, label: string, percent: int, negative: int, positive: int, total: int}>}
     */
    public function getQuestionnaireAspectBreakdown(string $type): array
    {
        $responses = QuestionnaireResponse::query()
            ->where('questionnaire_type', $type)
            ->select('ratings')
            ->cursor();

        $stats = [];

        foreach ($responses as $response) {
            foreach ((array) $response->ratings as $code => $scale) {
                if (! isset($stats[$code])) {
                    $stats[$code] = ['positive' => 0, 'total' => 0];
                }
                $stats[$code]['total']++;
                if (in_array($scale, self::POSITIVE_SCALES, true)) {
                    $stats[$code]['positive']++;
                }
            }
        }

        if ($stats === []) {
            return ['highest' => [], 'lowest' => []];
        }

        $items = [];
        foreach ($stats as $code => $counts) {
            $positivePercent = $counts['total'] === 0 ? 0 : (int) round($counts['positive'] / $counts['total'] * 100);
            $negative = $counts['total'] - $counts['positive'];
            $negativePercent = $counts['total'] === 0 ? 0 : (int) round($negative / $counts['total'] * 100);

            $items[] = [
                'code' => (string) $code,
                'label' => QuestionnaireDefinition::label((string) $code),
                'positive_percent' => $positivePercent,
                'negative_percent' => $negativePercent,
                'positive' => $counts['positive'],
                'negative' => $negative,
                'total' => $counts['total'],
            ];
        }

        $highest = array_map(fn (array $item): array => [
            'code' => $item['code'],
            'label' => $item['label'],
            'percent' => $item['positive_percent'],
            'positive' => $item['positive'],
            'total' => $item['total'],
        ], $items);
        usort($highest, fn (array $a, array $b): int => ($b['percent'] <=> $a['percent']) ?: ($b['positive'] <=> $a['positive']));

        $lowest = array_map(fn (array $item): array => [
            'code' => $item['code'],
            'label' => $item['label'],
            'percent' => $item['negative_percent'],
            'negative' => $item['negative'],
            'positive' => $item['positive'],
            'total' => $item['total'],
        ], $items);
        usort($lowest, function (array $a, array $b): int {
            return ($b['percent'] <=> $a['percent'])
                ?: ($b['negative'] <=> $a['negative'])
                ?: ($b['total'] <=> $a['total'])
                ?: ($a['positive'] <=> $b['positive']);
        });

        return [
            'highest' => array_slice($highest, 0, 3),
            'lowest' => array_slice($lowest, 0, 3),
        ];
    }

    /**
     * Hitung rasio jawaban positif untuk satu pengisian kuesioner.
     *
     * @param  array<string, mixed>  $ratings
     * @return array{positive: int, total: int, percent: int}
     */
    public function calculateResponseScore(array $ratings): array
    {
        $total = 0;
        $positive = 0;

        foreach ($ratings as $scale) {
            $total++;
            if (in_array($scale, self::POSITIVE_SCALES, true)) {
                $positive++;
            }
        }

        return [
            'positive' => $positive,
            'total' => $total,
            'percent' => $total === 0 ? 0 : (int) round($positive / $total * 100),
        ];
    }
}
