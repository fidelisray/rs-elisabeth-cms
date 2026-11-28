<div class="rse-lg-root" data-rse-logic-guide>
    <style>
        .rse-lg-root {
            --rse-lg-surface: var(--rse-slate-surface, #ffffff);
            --rse-lg-muted: var(--rse-slate-muted, #f8fafc);
            --rse-lg-border: var(--rse-slate-border, #cbd5e1);
            --rse-lg-text: var(--rse-slate-text, #0f172a);
            --rse-lg-text-secondary: var(--rse-slate-text-secondary, #475569);
            color: var(--rse-lg-text);
        }

        .dark .rse-lg-root {
            --rse-lg-surface: var(--rse-slate-surface, #0f172a);
            --rse-lg-muted: var(--rse-slate-muted, #1e293b);
            --rse-lg-border: var(--rse-slate-border, #334155);
            --rse-lg-text: var(--rse-slate-text, #f8fafc);
            --rse-lg-text-secondary: var(--rse-slate-text-secondary, #cbd5e1);
        }

        .rse-lg-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
        }

        @media (min-width: 900px) {
            .rse-lg-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        .rse-lg-card {
            padding: 0.875rem 1rem;
            border-radius: 0.625rem;
            background-color: var(--rse-lg-surface);
            border: 1px solid var(--rse-lg-border);
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
        }

        .rse-lg-card-primary {
            border-top: 3px solid var(--rse-brand-accent, #095988);
        }

        .rse-lg-card-secondary {
            border-top: 3px solid var(--rse-brand-secondary, #159e74);
        }

        .rse-lg-card-neutral {
            border-top: 3px solid var(--rse-slate-border-strong, #64748b);
        }

        .rse-lg-pill {
            display: inline-flex;
            align-self: flex-start;
            padding: 0.125rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.02em;
        }

        .rse-lg-pill-primary {
            background-color: var(--rse-brand-badge-bg, #dceff9);
            color: var(--rse-brand-badge-text, #07476e);
        }

        .rse-lg-pill-secondary {
            background-color: #d1fae5;
            color: #094d38;
        }

        .dark .rse-lg-pill-secondary {
            background-color: #052e21;
            color: #a7f3d0;
        }

        .rse-lg-pill-neutral {
            background-color: var(--rse-lg-muted);
            color: var(--rse-lg-text-secondary);
            border: 1px solid var(--rse-lg-border);
        }

        .rse-lg-title {
            font-size: 0.875rem;
            font-weight: 700;
            margin: 0;
        }

        .rse-lg-desc {
            font-size: 0.75rem;
            line-height: 1.45;
            color: var(--rse-lg-text-secondary);
            margin: 0;
        }

        .rse-lg-example {
            margin: 0.25rem 0 0;
            padding: 0.5rem 0.625rem;
            border-radius: 0.375rem;
            background-color: var(--rse-lg-muted);
            font-size: 0.75rem;
            line-height: 1.4;
            color: var(--rse-lg-text);
        }

        .rse-lg-callout {
            margin-top: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 0.5rem;
            background-color: var(--rse-brand-soft-bg, #f0f8fc);
            border: 1px solid var(--rse-brand-200, #bae0f4);
            color: var(--rse-lg-text);
            font-size: 0.8125rem;
            line-height: 1.45;
        }

        .dark .rse-lg-callout {
            border-color: rgba(25, 151, 230, 0.35);
        }
    </style>

    <div class="rse-lg-grid">
        <div class="rse-lg-card rse-lg-card-primary">
            <span class="rse-lg-pill rse-lg-pill-primary">{{ __('Level Pertanyaan · Langsung') }}</span>
            <p class="rse-lg-title">{{ __('1. Tampil / Sembunyi Pertanyaan') }}</p>
            <p class="rse-lg-desc">
                {!! __('Memunculkan atau menyembunyikan :highlight berdasarkan jawaban pertanyaan sebelumnya (mendukung kombinasi AND/OR dan semua tipe input).', ['highlight' => '<strong>'.e(__('pertanyaan tertentu secara real-time')).'</strong>']) !!}
            </p>
            <p class="rse-lg-example">
                <strong>{{ __('Contoh:') }}</strong> {{ __('Jika "Pakai Asuransi?" = Ya → muncul isian "Nama Asuransi" tepat di bawahnya.') }}
            </p>
        </div>

        <div class="rse-lg-card rse-lg-card-secondary">
            <span class="rse-lg-pill rse-lg-pill-secondary">{{ __('Level Halaman · Saat Klik Lanjut') }}</span>
            <p class="rse-lg-title">{{ __('2. Lompatan Bagian per Jawaban') }}</p>
            <p class="rse-lg-desc">
                {!! __('Mengalihkan responden :highlight atau langsung mengirim formulir berdasarkan opsi Radio/Dropdown (maksimal 1 pertanyaan pemicu per Bagian).', ['highlight' => '<strong>'.e(__('melompati halaman/Bagian')).'</strong>']) !!}
            </p>
            <p class="rse-lg-example">
                <strong>{{ __('Contoh:') }}</strong> {{ __('Pilih "IGD" → lompat ke Bagian 2 (IGD); pilih "Rawat Jalan" → lompat ke Bagian 3.') }}
            </p>
        </div>

        <div class="rse-lg-card rse-lg-card-neutral">
            <span class="rse-lg-pill rse-lg-pill-neutral">{{ __('Pasangan Menu 2 · Tujuan Default') }}</span>
            <p class="rse-lg-title">{{ __('3. Aturan Lanjut di Akhir Bagian') }}</p>
            <p class="rse-lg-desc">
                {!! __('Menentukan tujuan :highlight apabila tidak ada lompatan Menu 2 yang terpicu di Bagian tersebut.', ['highlight' => '<strong>'.e(__('setelah responden selesai mengisi satu Bagian')).'</strong>']) !!}
            </p>
            <p class="rse-lg-example">
                <strong>{{ __('Contoh:') }}</strong> {{ __('Setelah selesai Bagian 2 (IGD) → lompat ke Bagian 4 (Penutup) agar tidak masuk ke Rawat Jalan.') }}
            </p>
        </div>
    </div>

    @if ($sectionCount < 2)
        <div class="rse-lg-callout" role="note">
            <strong>{{ __('Catatan Struktur Bagian:') }}</strong>
            {{ __('Formulir ini saat ini baru memiliki :count Bagian. Anda dapat langsung memakai Menu 1 (Tampil/Sembunyi Pertanyaan) di dalam Bagian yang sama, sedangkan Menu 2 & Menu 3 baru dapat melompat antar-halaman setelah Anda menambahkan minimal 2 Bagian di Langkah 2 (atau gunakan opsi Kirim Formulir (Selesai)).', ['count' => $sectionCount]) }}
        </div>
    @endif
</div>
