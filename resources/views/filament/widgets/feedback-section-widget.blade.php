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
    @endphp

    <style>
        .rse-fb-root {
            --rse-surface: #ffffff;
            --rse-surface-muted: #f8fafc;
            --rse-border: #cbd5e1;
            --rse-border-subtle: #e2e8f0;
            --rse-text: #0f172a;
            --rse-text-secondary: #475569;
            --rse-focus: #b45309;

            --rse-amber-text: #b45309;
            --rse-amber-bg: #fef3c7;
            --rse-amber-badge-text: #92400e;
            --rse-amber-border: #f59e0b;

            --rse-sky-text: #0369a1;
            --rse-sky-bg: #e0f2fe;
            --rse-sky-badge-text: #075985;
            --rse-sky-border: #0284c7;

            --rse-emerald-text: #047857;
            --rse-emerald-bg: #d1fae5;
            --rse-emerald-badge-text: #065f46;
            --rse-emerald-border: #059669;

            color: var(--rse-text);
            font-variant-numeric: tabular-nums;
        }

        .dark .rse-fb-root {
            --rse-surface: #18181b;
            --rse-surface-muted: #0f172a;
            --rse-border: #3f3f46;
            --rse-border-subtle: #27272a;
            --rse-text: #f8fafc;
            --rse-text-secondary: #cbd5e1;
            --rse-focus: #fbbf24;

            --rse-amber-text: #fbbf24;
            --rse-amber-bg: #451a03;
            --rse-amber-badge-text: #fde68a;
            --rse-amber-border: #d97706;

            --rse-sky-text: #38bdf8;
            --rse-sky-bg: #082f49;
            --rse-sky-badge-text: #bae6fd;
            --rse-sky-border: #0284c7;

            --rse-emerald-text: #34d399;
            --rse-emerald-bg: #064e3b;
            --rse-emerald-badge-text: #a7f3d0;
            --rse-emerald-border: #059669;
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
            background-color: #b45309;
            color: #ffffff;
            font-size: 0.875rem;
            font-weight: 600;
            text-decoration: none;
            border: 1px solid transparent;
            transition: background-color 150ms ease;
        }

        .dark .rse-fb-cta-link {
            background-color: #fbbf24;
            color: #0f172a;
        }

        .rse-fb-cta-link:hover {
            background-color: #92400e;
        }

        .dark .rse-fb-cta-link:hover {
            background-color: #f59e0b;
        }

        .rse-fb-cta-link:focus-visible,
        .rse-fb-kpi-card:focus-visible,
        .rse-fb-reset-btn:focus-visible,
        .rse-fb-ticket-link:focus-visible,
        .rse-fb-action-btn:focus-visible {
            outline: 2px solid var(--rse-focus);
            outline-offset: 2px;
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
            color: var(--rse-amber-text);
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
        <x-slot name="heading">Kritik & Saran Pasien</x-slot>
        <x-slot name="description">
            Ringkasan dari formulir kritik dan saran di website. Nama dan nomor telepon pasien tidak ditampilkan di sini.
        </x-slot>

        <div class="rse-fb-root" x-data="{ statusFilter: 'all' }">
            <div class="rse-fb-topbar">
                <div class="rse-fb-privacy-pill">
                    <span>Data pribadi pelapor & pasien disamarkan pada tampilan ringkasan ini</span>
                </div>
                <a class="rse-fb-cta-link" href="{{ $indexUrl }}">
                    Lihat seluruh kritik dan saran
                </a>
            </div>

            <div class="rse-fb-kpi-grid" role="group" aria-label="Filter ringkasan status kritik dan saran">
                <button
                    type="button"
                    class="rse-fb-kpi-card"
                    :aria-pressed="statusFilter === 'all' ? 'true' : 'false'"
                    @click="statusFilter = 'all'"
                >
                    <div class="rse-fb-kpi-header">
                        <span class="rse-fb-kpi-label">Total</span>
                        <span class="rse-fb-kpi-pct">100%</span>
                    </div>
                    <p class="rse-fb-kpi-value">{{ $totalCount }}</p>
                    <p class="rse-fb-kpi-sub">Semua laporan masuk</p>
                </button>

                <button
                    type="button"
                    class="rse-fb-kpi-card"
                    :aria-pressed="statusFilter === 'baru' ? 'true' : 'false'"
                    @click="statusFilter = (statusFilter === 'baru' ? 'all' : 'baru')"
                >
                    <div class="rse-fb-kpi-header">
                        <span class="rse-fb-kpi-label">Baru</span>
                        <span class="rse-fb-kpi-pct">{{ $baruPct }}%</span>
                    </div>
                    <p class="rse-fb-kpi-value" style="color: var(--rse-amber-text);">{{ $baruCount }}</p>
                    <p class="rse-fb-kpi-sub">Menunggu triase awal</p>
                </button>

                <button
                    type="button"
                    class="rse-fb-kpi-card"
                    :aria-pressed="statusFilter === 'diproses' ? 'true' : 'false'"
                    @click="statusFilter = (statusFilter === 'diproses' ? 'all' : 'diproses')"
                >
                    <div class="rse-fb-kpi-header">
                        <span class="rse-fb-kpi-label">Diproses</span>
                        <span class="rse-fb-kpi-pct">{{ $diprosesPct }}%</span>
                    </div>
                    <p class="rse-fb-kpi-value" style="color: var(--rse-sky-text);">{{ $diprosesCount }}</p>
                    <p class="rse-fb-kpi-sub">Sedang ditindaklanjuti unit</p>
                </button>

                <button
                    type="button"
                    class="rse-fb-kpi-card"
                    :aria-pressed="statusFilter === 'selesai' ? 'true' : 'false'"
                    @click="statusFilter = (statusFilter === 'selesai' ? 'all' : 'selesai')"
                >
                    <div class="rse-fb-kpi-header">
                        <span class="rse-fb-kpi-label">Selesai</span>
                        <span class="rse-fb-kpi-pct">{{ $selesaiPct }}%</span>
                    </div>
                    <p class="rse-fb-kpi-value" style="color: var(--rse-emerald-text);">{{ $selesaiCount }}</p>
                    <p class="rse-fb-kpi-sub">Penanganan tuntas ({{ $selesaiPct }}%)</p>
                </button>
            </div>

            @if ($totalCount > 0)
                <div class="rse-fb-progress-box" aria-label="Proporsi penyelesaian laporan">
                    <div class="rse-fb-progress-head">
                        <span style="font-weight: 600; color: var(--rse-text);">Distribusi Penyelesaian Tiket</span>
                        <span>Selesai {{ $selesaiPct }}% · Diproses {{ $diprosesPct }}% · Baru {{ $baruPct }}%</span>
                    </div>
                    <div class="rse-fb-progress-track">
                        @if ($selesaiPct > 0)
                            <div style="width: {{ $selesaiPct }}%; background-color: #059669;" title="Selesai: {{ $selesaiCount }} ({{ $selesaiPct }}%)"></div>
                        @endif
                        @if ($diprosesPct > 0)
                            <div style="width: {{ $diprosesPct }}%; background-color: #0284c7;" title="Diproses: {{ $diprosesCount }} ({{ $diprosesPct }}%)"></div>
                        @endif
                        @if ($baruPct > 0)
                            <div style="width: {{ $baruPct }}%; background-color: #d97706;" title="Baru: {{ $baruCount }} ({{ $baruPct }}%)"></div>
                        @endif
                    </div>
                </div>
            @endif

            @if (count($latest) === 0)
                <div class="rse-fb-empty">
                    <p style="font-size: 0.9375rem; font-weight: 600; color: var(--rse-text); margin-bottom: 0.25rem;">
                        Belum ada kritik dan saran masuk.
                    </p>
                    <p style="font-size: 0.8125rem; color: var(--rse-text-secondary);">
                        Laporan yang dikirim pasien melalui formulir website akan otomatis muncul di daftar triase ini.
                    </p>
                </div>
            @else
                <div class="rse-fb-toolbar">
                    <div style="font-size: 0.875rem; font-weight: 600; color: var(--rse-text);">
                        <span>10 Laporan Terbaru</span>
                        <span x-show="statusFilter !== 'all'" x-cloak style="font-weight: 500; color: var(--rse-text-secondary);">
                            (Filter aktif: <span x-text="statusFilter.charAt(0).toUpperCase() + statusFilter.slice(1)"></span>)
                        </span>
                    </div>
                    <button
                        type="button"
                        x-show="statusFilter !== 'all'"
                        x-cloak
                        class="rse-fb-reset-btn"
                        @click="statusFilter = 'all'"
                    >
                        Tampilkan semua status
                    </button>
                </div>

                <div class="rse-fb-table-wrap">
                    <table class="rse-fb-table">
                        <thead>
                            <tr>
                                <th>Tiket</th>
                                <th>Kategori</th>
                                <th>Keluhan</th>
                                <th>Status</th>
                                <th>Waktu</th>
                                <th>Tindakan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($latest as $row)
                                @php
                                    $rowStatus = strtolower((string) ($row['status'] ?? 'baru'));
                                    $statusClass = match ($rowStatus) {
                                        'selesai' => 'rse-fb-status-selesai',
                                        'diproses' => 'rse-fb-status-diproses',
                                        default => 'rse-fb-status-baru',
                                    };
                                    $dotColor = match ($rowStatus) {
                                        'selesai' => '#059669',
                                        'diproses' => '#0284c7',
                                        default => '#d97706',
                                    };
                                    $categories = $row['category_list'] ?? array_filter(array_map('trim', explode(',', (string) ($row['categories'] ?? ''))));
                                @endphp
                                <tr x-show="statusFilter === 'all' || statusFilter === '{{ $rowStatus }}'">
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
                                            <span style="color: var(--rse-text-secondary); font-size: 0.8125rem;">Umum</span>
                                        @endif
                                    </td>
                                    <td style="max-width: 28rem; line-height: 1.45;">
                                        {{ \Illuminate\Support\Str::limit($row['detail'], 140) }}
                                    </td>
                                    <td>
                                        <span class="rse-fb-status-badge {{ $statusClass }}">
                                            <span class="rse-fb-status-dot" style="background-color: {{ $dotColor }};"></span>
                                            {{ ucfirst($row['status']) }}
                                        </span>
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
                                            Buka Tiket
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
