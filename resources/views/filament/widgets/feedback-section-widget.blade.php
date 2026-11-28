<x-filament-widgets::widget>
    @php
        $totalCount = (int) ($summary['total'] ?? 0);
        $baruCount = (int) ($summary['baru'] ?? 0);
        $diprosesCount = (int) ($summary['diproses'] ?? 0);
        $selesaiCount = (int) ($summary['selesai'] ?? 0);

        $baruPct = $totalCount > 0 ? (int) round(($baruCount / $totalCount) * 100) : 0;
        $diprosesPct = $totalCount > 0 ? (int) round(($diprosesCount / $totalCount) * 100) : 0;
        $selesaiPct = $totalCount > 0 ? (int) round(($selesaiCount / $totalCount) * 100) : 0;

        $indexUrl = \App\Filament\Resources\Feedbacks\FeedbackResource::getUrl('index');

        $categoryGroups = [
            [
                'key' => 'Pelayanan Medis (Tindakan medis, penegakan diagnosis, penundaan pelayanan, dll)',
                'label' => 'Pelayanan Medis',
            ],
            [
                'key' => 'Pelayanan Non Medis (Prosedur pendaftaran, pembayaran, klaim asuransi, dll)',
                'label' => 'Pelayanan Non Medis',
            ],
            [
                'key' => 'Fasilitas & Sarana Prasarana',
                'label' => 'Fasilitas & Sarana',
            ],
            [
                'key' => 'Tarif dan biaya pemeriksaan',
                'label' => 'Tarif & Biaya',
            ],
            [
                'key' => 'Etika dan perilaku petugas',
                'label' => 'Etika & Perilaku',
            ],
            [
                'key' => 'Lainnya',
                'label' => 'Lainnya',
            ],
        ];

        $categoryCounts = [];
        foreach ($categoryGroups as $groupItem) {
            $categoryCounts[$groupItem['key']] = 0;
        }

        foreach ($latest as $latestRow) {
            $rowCategories = $latestRow['category_list'] ?? array_filter(array_map('trim', explode(',', (string) ($latestRow['categories'] ?? ''))));
            foreach ($rowCategories as $catName) {
                if (! array_key_exists($catName, $categoryCounts)) {
                    $categoryCounts[$catName] = 0;
                    $categoryGroups[] = [
                        'key' => $catName,
                        'label' => $catName,
                    ];
                }
                $categoryCounts[$catName]++;
            }
        }
    @endphp

    <style>
        .rse-fb-root {
            --rse-surface: var(--rse-slate-surface, #ffffff);
            --rse-surface-muted: var(--rse-slate-muted, #f8fafc);
            --rse-border: var(--rse-slate-border, #cbd5e1);
            --rse-border-subtle: var(--rse-slate-divider, #e2e8f0);
            --rse-text: var(--rse-slate-text, #0f172a);
            --rse-text-secondary: var(--rse-slate-text-secondary, #475569);
            --rse-focus: var(--rse-brand-focus, #095988);

            --rse-amber-text: #b45309;
            --rse-amber-bg: #fef3c7;
            --rse-amber-badge-text: #92400e;
            --rse-amber-border: #f59e0b;

            --rse-sky-text: #0369a1;
            --rse-sky-bg: #e0f2fe;
            --rse-sky-badge-text: #075985;
            --rse-sky-border: #0284c7;

            --rse-emerald-text: #0f7a59;
            --rse-emerald-bg: #d1fae5;
            --rse-emerald-badge-text: #094d38;
            --rse-emerald-border: #159e74;

            color: var(--rse-text);
            font-variant-numeric: tabular-nums;
        }

        .dark .rse-fb-root {
            --rse-surface: var(--rse-slate-surface, #0f172a);
            --rse-surface-muted: var(--rse-slate-muted, #1e293b);
            --rse-border: var(--rse-slate-border, #334155);
            --rse-border-subtle: var(--rse-slate-divider, #233044);
            --rse-text: var(--rse-slate-text, #f8fafc);
            --rse-text-secondary: var(--rse-slate-text-secondary, #cbd5e1);
            --rse-focus: var(--rse-brand-focus, #1997e6);

            --rse-amber-text: #fbbf24;
            --rse-amber-bg: #451a03;
            --rse-amber-badge-text: #fde68a;
            --rse-amber-border: #d97706;

            --rse-sky-text: #38bdf8;
            --rse-sky-bg: #082f49;
            --rse-sky-badge-text: #bae6fd;
            --rse-sky-border: #0284c7;

            --rse-emerald-text: #34d399;
            --rse-emerald-bg: #052e21;
            --rse-emerald-badge-text: #a7f3d0;
            --rse-emerald-border: #159e74;
        }

        .rse-fb-topbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--rse-border-subtle);
        }

        .rse-fb-privacy-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            background-color: var(--rse-surface-muted);
            border: 1px solid var(--rse-border);
            color: var(--rse-text-secondary);
            font-size: 0.8125rem;
            line-height: 1.25rem;
            font-weight: 500;
        }

        .rse-fb-cta-link {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            background-color: var(--rse-brand-accent, #095988);
            color: var(--rse-brand-accent-text, #ffffff);
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
            transition: background-color 150ms ease;
        }

        .rse-fb-cta-link:hover {
            background-color: var(--rse-brand-accent-hover, #07476e);
        }

        .rse-fb-cta-link:focus-visible,
        .rse-fb-kpi-card:focus-visible,
        .rse-fb-filter-pill:focus-visible,
        .rse-fb-reset-btn:focus-visible,
        .rse-fb-ticket-link:focus-visible,
        .rse-fb-action-btn:focus-visible {
            outline: 2px solid var(--rse-focus);
            outline-offset: 2px;
        }

        .rse-fb-filter-box {
            padding: 1rem;
            border-radius: 0.625rem;
            background-color: var(--rse-surface-muted);
            border: 1px solid var(--rse-border);
            margin-bottom: 1.25rem;
        }

        .rse-fb-filter-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
        }

        .rse-fb-filter-title {
            font-size: 0.8125rem;
            font-weight: 700;
            color: var(--rse-text);
        }

        .rse-fb-filter-subtitle {
            font-size: 0.75rem;
            color: var(--rse-text-secondary);
        }

        .rse-fb-filter-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .rse-fb-filter-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            min-height: 44px;
            padding: 0.4375rem 0.875rem;
            border-radius: 0.5rem;
            background-color: var(--rse-surface);
            border: 1px solid var(--rse-border);
            color: var(--rse-text);
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 150ms ease, border-color 150ms ease, color 150ms ease;
        }

        .rse-fb-filter-pill:hover {
            border-color: var(--rse-focus);
        }

        .rse-fb-filter-pill[aria-pressed="true"] {
            background-color: var(--rse-brand-accent, #095988);
            border-color: var(--rse-brand-accent, #095988);
            color: var(--rse-brand-accent-text, #ffffff);
        }

        .rse-fb-filter-count {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 1.375rem;
            padding: 0.0625rem 0.375rem;
            border-radius: 0.25rem;
            background-color: var(--rse-surface-muted);
            border: 1px solid var(--rse-border);
            color: var(--rse-text-secondary);
            font-size: 0.75rem;
            font-weight: 700;
        }

        .rse-fb-filter-pill[aria-pressed="true"] .rse-fb-filter-count {
            background-color: var(--rse-brand-accent-hover, #07476e);
            border-color: transparent;
            color: var(--rse-brand-accent-text, #ffffff);
        }

        .rse-fb-kpi-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        @media (min-width: 560px) {
            .rse-fb-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 0.875rem;
            }
        }

        @media (min-width: 960px) {
            .rse-fb-kpi-grid {
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 1rem;
            }
        }

        .rse-fb-kpi-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 100%;
            min-height: 104px;
            padding: 1rem;
            text-align: left;
            background-color: var(--rse-surface);
            border: 1px solid var(--rse-border);
            border-radius: 0.625rem;
            cursor: pointer;
            transition: border-color 150ms ease, background-color 150ms ease;
        }

        .rse-fb-kpi-card:hover {
            background-color: var(--rse-surface-muted);
        }

        .rse-fb-kpi-card[aria-pressed="true"] {
            border-width: 2px;
            border-color: var(--rse-focus);
            background-color: var(--rse-surface-muted);
        }

        .rse-fb-kpi-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        .rse-fb-kpi-label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--rse-text-secondary);
        }

        .rse-fb-kpi-pct {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.125rem 0.5rem;
            border-radius: 0.25rem;
            background-color: var(--rse-surface-muted);
            border: 1px solid var(--rse-border);
            color: var(--rse-text-secondary);
        }

        .rse-fb-kpi-value {
            font-size: 1.75rem;
            line-height: 2rem;
            font-weight: 700;
            color: var(--rse-text);
            margin-bottom: 0.25rem;
        }

        .rse-fb-kpi-sub {
            font-size: 0.75rem;
            color: var(--rse-text-secondary);
        }

        .rse-fb-progress-box {
            padding: 0.875rem 1rem;
            border-radius: 0.625rem;
            background-color: var(--rse-surface-muted);
            border: 1px solid var(--rse-border);
            margin-bottom: 1.25rem;
        }

        .rse-fb-progress-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-size: 0.8125rem;
            color: var(--rse-text-secondary);
        }

        .rse-fb-progress-track {
            display: flex;
            width: 100%;
            height: 0.625rem;
            border-radius: 9999px;
            overflow: hidden;
            background-color: var(--rse-border-subtle);
        }

        .rse-fb-toolbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 0.75rem;
        }

        .rse-fb-reset-btn {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            padding: 0.375rem 0.875rem;
            border-radius: 0.375rem;
            background-color: var(--rse-surface-muted);
            border: 1px solid var(--rse-border);
            color: var(--rse-text);
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
        }

        .rse-fb-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--rse-border);
            border-radius: 0.625rem;
            background-color: var(--rse-surface);
        }

        .rse-fb-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.875rem;
        }

        .rse-fb-table th {
            padding: 0.75rem 1rem;
            background-color: var(--rse-surface-muted);
            border-bottom: 1px solid var(--rse-border);
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            color: var(--rse-text-secondary);
            white-space: nowrap;
        }

        .rse-fb-table td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--rse-border-subtle);
            vertical-align: top;
            color: var(--rse-text);
        }

        .rse-fb-table tbody tr:last-child td {
            border-bottom: none;
        }

        .rse-fb-table tbody tr:hover td {
            background-color: var(--rse-surface-muted);
        }

        .rse-fb-ticket-link {
            display: inline-flex;
            align-items: center;
            min-height: 44px;
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-weight: 700;
            color: var(--rse-brand-link, #095988);
            text-decoration: underline;
            text-underline-offset: 3px;
        }

        .rse-fb-cat-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.375rem;
        }

        .rse-fb-cat-tag {
            display: inline-block;
            padding: 0.1875rem 0.5rem;
            border-radius: 0.25rem;
            background-color: var(--rse-surface-muted);
            border: 1px solid var(--rse-border);
            color: var(--rse-text);
            font-size: 0.75rem;
            font-weight: 500;
            line-height: 1.25;
        }

        .rse-fb-status-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.625rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 700;
            white-space: nowrap;
            border: 1px solid transparent;
        }

        .rse-fb-status-dot {
            width: 0.5rem;
            height: 0.5rem;
            border-radius: 9999px;
        }

        .rse-fb-status-baru {
            background-color: var(--rse-amber-bg);
            color: var(--rse-amber-badge-text);
            border-color: var(--rse-amber-border);
        }

        .rse-fb-status-diproses {
            background-color: var(--rse-sky-bg);
            color: var(--rse-sky-badge-text);
            border-color: var(--rse-sky-border);
        }

        .rse-fb-status-selesai {
            background-color: var(--rse-emerald-bg);
            color: var(--rse-emerald-badge-text);
            border-color: var(--rse-emerald-border);
        }

        .rse-fb-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            min-height: 44px;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            background-color: var(--rse-surface-muted);
            border: 1px solid var(--rse-border);
            color: var(--rse-text);
            font-size: 0.8125rem;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
        }

        .rse-fb-action-btn:hover {
            border-color: var(--rse-focus);
        }

        .rse-fb-empty {
            padding: 2rem 1.25rem;
            border-radius: 0.625rem;
            border: 1px dashed var(--rse-border);
            background-color: var(--rse-surface-muted);
            text-align: center;
        }
    </style>

    <x-filament::section>
        <x-slot name="heading">{{ __('Kritik & Saran Pasien') }}</x-slot>
        <x-slot name="description">
            {{ __('Ringkasan dari formulir kritik dan saran di website. Nama dan nomor telepon pasien tidak ditampilkan di sini.') }}
        </x-slot>

        <div class="rse-fb-root" x-data="{ statusFilter: 'all', categoryFilter: 'all', categoryLabel: @js(__('Semua Kategori')) }">
            <div class="rse-fb-topbar">
                <div class="rse-fb-privacy-pill">
                    <span>{{ __('Data pribadi pelapor & pasien disamarkan pada tampilan ringkasan ini') }}</span>
                </div>
                <a class="rse-fb-cta-link" href="{{ $indexUrl }}">
                    {{ __('Lihat seluruh kritik dan saran') }}
                </a>
            </div>

            <div class="rse-fb-filter-box" role="group" aria-label="{{ __('Filter kelompok kategori kritik dan saran') }}">
                <div class="rse-fb-filter-head">
                    <div>
                        <p class="rse-fb-filter-title">{{ __('Filter Kelompok Kategori') }}</p>
                        <p class="rse-fb-filter-subtitle">{{ __('Pilih kelompok kategori untuk menyaring daftar kritik dan saran pasien di bawah ini.') }}</p>
                    </div>
                    <span class="rse-fb-privacy-pill" style="padding: 0.25rem 0.625rem;">
                        {{ __('Total laporan tersimpan:') }} <strong>{{ $totalCount }}</strong>
                    </span>
                </div>

                <div class="rse-fb-filter-list">
                    <button
                        type="button"
                        class="rse-fb-filter-pill"
                        :aria-pressed="categoryFilter === 'all' ? 'true' : 'false'"
                        @click="categoryFilter = 'all'; categoryLabel = @js(__('Semua Kategori'))"
                    >
                        <span>{{ __('Semua Kategori') }}</span>
                        <span class="rse-fb-filter-count">{{ count($latest) }}</span>
                    </button>

                    @foreach ($categoryGroups as $catGroup)
                        @php
                            $catKey = $catGroup['key'];
                            $catNameLabel = $catGroup['label'];
                            $catCount = $categoryCounts[$catKey] ?? 0;
                        @endphp
                        <button
                            type="button"
                            class="rse-fb-filter-pill"
                            data-cat-key="{{ $catKey }}"
                            data-cat-label="{{ $catNameLabel }}"
                            :aria-pressed="categoryFilter === $el.dataset.catKey ? 'true' : 'false'"
                            @click="if (categoryFilter === $el.dataset.catKey) { categoryFilter = 'all'; categoryLabel = @js(__('Semua Kategori')); } else { categoryFilter = $el.dataset.catKey; categoryLabel = $el.dataset.catLabel; }"
                        >
                            <span>{{ $catNameLabel }}</span>
                            <span class="rse-fb-filter-count">{{ $catCount }}</span>
                        </button>
                    @endforeach
                </div>
            </div>

            @if (count($latest) === 0)
                <div class="rse-fb-empty">
                    <p style="font-size: 0.9375rem; font-weight: 600; color: var(--rse-text); margin-bottom: 0.25rem;">
                        {{ __('Belum ada kritik dan saran masuk.') }}
                    </p>
                    <p style="font-size: 0.8125rem; color: var(--rse-text-secondary);">
                        {{ __('Laporan yang dikirim pasien melalui formulir website akan otomatis muncul di daftar ini.') }}
                    </p>
                </div>
            @else
                <div class="rse-fb-toolbar">
                    <div style="font-size: 0.875rem; font-weight: 600; color: var(--rse-text);">
                        <span>{{ __('10 Laporan Terbaru') }}</span>
                        <span x-show="categoryFilter !== 'all'" x-cloak style="font-weight: 500; color: var(--rse-text-secondary);">
                            ({{ __('Kategori') }}: <span x-text="categoryLabel"></span>)
                        </span>
                    </div>
                    <button
                        type="button"
                        x-show="categoryFilter !== 'all'"
                        x-cloak
                        class="rse-fb-reset-btn"
                        @click="categoryFilter = 'all'; categoryLabel = @js(__('Semua Kategori'))"
                    >
                        {{ __('Tampilkan semua kategori') }}
                    </button>
                </div>

                <div class="rse-fb-table-wrap">
                    <table class="rse-fb-table">
                        <thead>
                            <tr>
                                <th>{{ __('Kode') }}</th>
                                <th>{{ __('Kategori') }}</th>
                                <th>{{ __('Keluhan') }}</th>
                                <th>{{ __('Waktu') }}</th>
                                <th>{{ __('Tindakan') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($latest as $row)
                                @php
                                    $categories = array_values($row['category_list'] ?? array_filter(array_map('trim', explode(',', (string) ($row['categories'] ?? '')))));
                                @endphp
                                <tr
                                    data-categories="{{ json_encode($categories) }}"
                                    x-show="categoryFilter === 'all' || JSON.parse($el.dataset.categories || '[]').includes(categoryFilter)"
                                >
                                    <td style="white-space: nowrap;">
                                        <a class="rse-fb-ticket-link" href="{{ $row['url'] }}">
                                            {{ $row['ticket'] }}
                                        </a>
                                    </td>
                                    <td>
                                        @if (count($categories) > 0)
                                            <div class="rse-fb-cat-list">
                                                @foreach ($categories as $category)
                                                    <span class="rse-fb-cat-tag">{{ $category }}</span>
                                                @endforeach
                                            </div>
                                        @else
                                            <span style="color: var(--rse-text-secondary); font-size: 0.8125rem;">{{ __('Umum') }}</span>
                                        @endif
                                    </td>
                                    <td style="max-width: 28rem; line-height: 1.45;">
                                        {{ \Illuminate\Support\Str::limit($row['detail'], 140) }}
                                    </td>
                                    <td style="white-space: nowrap;">
                                        <div style="font-weight: 600;">{{ $row['submitted'] }}</div>
                                        @if (! empty($row['submitted_human']))
                                            <div style="font-size: 0.75rem; color: var(--rse-text-secondary); margin-top: 0.125rem;">
                                                {{ $row['submitted_human'] }}
                                            </div>
                                        @endif
                                    </td>
                                    <td style="white-space: nowrap;">
                                        <a class="rse-fb-action-btn" href="{{ $row['url'] }}">
                                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="width: 16px; height: 16px; flex-shrink: 0;">
                                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"/>
                                                <circle cx="12" cy="12" r="3"/>
                                            </svg>
                                            <span>{{ __('Lihat') }}</span>
                                        </a>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
