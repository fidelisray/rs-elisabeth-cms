<?php

namespace App\Services;

/**
 * FeedbackService
 *
 * Service ini bertanggung jawab sebagai single source of truth untuk seluruh
 * data feedback/ulasan pelanggan RS Elisabeth.
 *
 * ARSITEKTUR:
 * Widget Filament (FeedbackStatsWidget, LatestFeedbackWidget) tidak mengakses
 * data secara langsung. Mereka hanya memanggil service ini.
 *
 * ROADMAP INTEGRASI:
 * Saat ini service ini menggunakan dummy data.
 * Ketika API Internal RS sudah siap, hanya isi method getStats() dan
 * getLatestFeedback() yang perlu diganti. Widget dan view tidak perlu disentuh.
 * Lihat: feedback_api_integration_plan.md untuk panduan lengkapnya.
 */
class FeedbackService
{
    /**
     * Mengambil data statistik agregat feedback.
     *
     * @return array{average_rating: float, total_reviews: int, satisfaction_percent: int}
     */
    public function getStats(): array
    {
        // ── DUMMY DATA ──────────────────────────────────────────────────────
        // Untuk integrasi ke API Internal RS, hapus return di bawah ini dan
        // ganti dengan Http::get() ke endpoint stats. Lihat integration plan.
        return [
            'average_rating' => 4.7,
            'total_reviews' => 218,
            'satisfaction_percent' => 91,
        ];
        // ── AKHIR DUMMY DATA ─────────────────────────────────────────────────
    }

    /**
     * Mengambil daftar feedback terbaru.
     *
     * Setiap item wajib memiliki struktur key berikut (kontrak data):
     * - name    : string  (nama pengirim / pasien)
     * - rating  : int     (nilai 1–5)
     * - comment : string  (teks ulasan)
     * - date    : string  (tanggal dalam format Y-m-d atau string terbaca)
     *
     * PENTING: Saat integrasi ke API nyata, pastikan response API
     * di-mapping ke struktur key yang sama persis seperti di atas.
     *
     * @param  int  $limit  Jumlah maksimum data yang diambil
     * @return array<int, array{name: string, rating: int, comment: string, date: string}>
     */
    public function getLatestFeedback(int $limit = 10): array
    {
        // ── DUMMY DATA ──────────────────────────────────────────────────────
        // Untuk integrasi ke API Internal RS, hapus return di bawah ini dan
        // ganti dengan Http::get() ke endpoint feedback. Lihat integration plan.
        $dummyData = [
            [
                'name' => 'Budi Santoso',
                'rating' => 5,
                'comment' => 'Pelayanan dokter dan perawat sangat ramah, saya merasa nyaman selama dirawat.',
                'date' => '2026-08-27',
            ],
            [
                'name' => 'Siti Rahayu',
                'rating' => 4,
                'comment' => 'Proses pendaftaran cukup cepat, ruang tunggu bersih dan nyaman.',
                'date' => '2026-08-26',
            ],
            [
                'name' => 'Agus Priyono',
                'rating' => 5,
                'comment' => 'Dokter menjelaskan kondisi saya dengan sangat detail dan sabar. Terima kasih!',
                'date' => '2026-08-25',
            ],
            [
                'name' => 'Dewi Kurniawati',
                'rating' => 3,
                'comment' => 'Waktu tunggu di poli cukup lama, sekitar 2 jam. Semoga bisa lebih cepat.',
                'date' => '2026-08-24',
            ],
            [
                'name' => 'Hendra Wijaya',
                'rating' => 5,
                'comment' => 'Fasilitas kamar rawat inap sangat bagus dan bersih. Sangat puas!',
                'date' => '2026-08-23',
            ],
            [
                'name' => 'Rina Marlina',
                'rating' => 4,
                'comment' => 'Perawat shift malam sangat responsif ketika saya butuh bantuan.',
                'date' => '2026-08-22',
            ],
            [
                'name' => 'Joko Purnomo',
                'rating' => 5,
                'comment' => 'Sudah 3x berobat ke sini, pelayanannya selalu konsisten baik.',
                'date' => '2026-08-21',
            ],
            [
                'name' => 'Fitri Handayani',
                'rating' => 4,
                'comment' => 'Proses administrasi BPJS dibantu dengan jelas oleh petugas.',
                'date' => '2026-08-20',
            ],
            [
                'name' => 'Anton Susilo',
                'rating' => 2,
                'comment' => 'Parkiran sangat penuh dan sempit, agak kesulitan saat membawa pasien.',
                'date' => '2026-08-19',
            ],
            [
                'name' => 'Lestari Wulandari',
                'rating' => 5,
                'comment' => 'Dokter spesialis jantungnya luar biasa, sangat profesional dan berdedikasi.',
                'date' => '2026-08-18',
            ],
        ];

        return array_slice($dummyData, 0, $limit);
        // ── AKHIR DUMMY DATA ─────────────────────────────────────────────────
    }
}
