<x-filament-widgets::widget>
    @php
        $indexUrl = \App\Filament\Resources\Questionnaires\QuestionnaireResource::getUrl('index');
        $totalAllUnits = collect($groups)->sum(fn ($g) => (int) ($g['total'] ?? 0));
    @endphp

    <style>
        .rse-qs-root {
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

            --rse-emerald-text: #047857;
            --rse-emerald-bg: #d1fae5;
            --rse-emerald-badge-text: #065f46;
            --rse-emerald-border: #059669;

            --rse-rose-text: #be123c;
            --rse-rose-bg: #ffe4e6;
            --rse-rose-badge-text: #9f1239;
            --rse-rose-border: #e11d48;

            color: var(--rse-text);
            font-variant-numeric: tabular-nums;
        }

        .dark .rse-qs-root {
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

            --rse-emerald-text: #34d399;
            --rse-emerald-bg: #064e3b;
            --rse-emerald-badge-text: #a7f3d0;
            --rse-emerald-border: #059669;

            --rse-rose-text: #fda4af;
            --rse-rose-bg: #4c0519;
            --rse-rose-badge-text: #fecdd3;
            --rse-rose-border: #f43f5e;
        }

        .rse-qs-topbar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 1.25rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--rse-border-subtle);
        }

        .rse-qs-summary-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.375rem 0.75rem;
            border-radius: 0.375rem;
            background-color: var(--rse-surface-muted);
            border: 1px solid var(--rse-border);
            color: var(--rse-text-secondary);
            font-size: 0.8125rem;
            font-weight: 500;
        }

        .rse-qs-cta-link {
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
            transition: background-color 150ms ease;
        }

        .dark .rse-qs-cta-link {
            background-color: #fbbf24;
            color: #0f172a;
        }

        .rse-qs-cta-link:hover {
            background-color: #92400e;
        }

        .dark .rse-qs-cta-link:hover {
            background-color: #f59e0b;
        }

        .rse-qs-cta-link:focus-visible,
        .rse-qs-tab-btn:focus-visible,
        .rse-qs-read-btn:focus-visible {
            outline: 2px solid var(--rse-focus);
            outline-offset: 2px;
        }

        .rse-qs-tablist {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.625rem;
            margin-bottom: 1.25rem;
        }

        @media (min-width: 600px) {
            .rse-qs-tablist {
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 0.75rem;
            }
        }

        .rse-qs-tab-btn {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            min-height: 48px;
            padding: 0.625rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid var(--rse-border);
            background-color: var(--rse-surface);
            color: var(--rse-text);
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            text-align: left;
            transition: border-color 150ms ease, background-color 150ms ease;
        }

        .rse-qs-tab-btn:hover {
            background-color: var(--rse-surface-muted);
        }

        .rse-qs-tab-btn[aria-selected="true"] {
            background-color: #b45309;
            border-color: #b45309;
            color: #ffffff;
        }

        .dark .rse-qs-tab-btn[aria-selected="true"] {
            background-color: #fbbf24;
            border-color: #fbbf24;
            color: #0f172a;
        }

        .rse-qs-tab-meta {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.75rem;
            font-weight: 700;
            padding: 0.1875rem 0.5rem;
            border-radius: 0.25rem;
            background-color: var(--rse-surface-muted);
            color: var(--rse-text-secondary);
            border: 1px solid var(--rse-border);
        }

        .rse-qs-tab-btn[aria-selected="true"] .rse-qs-tab-meta {
            background-color: #92400e;
            color: #ffffff;
            border-color: transparent;
        }

        .dark .rse-qs-tab-btn[aria-selected="true"] .rse-qs-tab-meta {
            background-color: #f59e0b;
            color: #0f172a;
            border-color: transparent;
        }

        .rse-qs-kpi-row {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.875rem;
            margin-bottom: 1.25rem;
        }

        @media (min-width: 600px) {
            .rse-qs-kpi-row {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 1rem;
            }
        }

        .rse-qs-kpi-card {
            padding: 1rem 1.125rem;
            border-radius: 0.625rem;
            background-color: var(--rse-surface);
            border: 1px solid var(--rse-border);
        }

        .rse-qs-kpi-label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--rse-text-secondary);
            margin-bottom: 0.25rem;
        }

        .rse-qs-kpi-value {
            font-size: 1.75rem;
            line-height: 2.125rem;
            font-weight: 700;
            color: var(--rse-text);
        }

        .rse-qs-kpi-note {
            font-size: 0.75rem;
            color: var(--rse-text-secondary);
            margin-top: 0.375rem;
        }

        .rse-qs-bar-track {
            width: 100%;
            height: 0.5rem;
            border-radius: 9999px;
            background-color: var(--rse-border-subtle);
            overflow: hidden;
            margin-top: 0.5rem;
        }

        .rse-qs-analytics-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
            margin-bottom: 1.25rem;
        }

        @media (min-width: 860px) {
            .rse-qs-analytics-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .rse-qs-panel {
            padding: 1rem 1.125rem;
            border-radius: 0.625rem;
            background-color: var(--rse-surface-muted);
            border: 1px solid var(--rse-border);
        }

        .rse-qs-panel-title {
            font-size: 0.875rem;
            font-weight: 700;
            color: var(--rse-text);
            margin-bottom: 0.75rem;
        }

        .rse-qs-dist-list {
            display: flex;
            flex-direction: column;
            gap: 0.625rem;
        }

        .rse-qs-dist-item-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            font-size: 0.8125rem;
            margin-bottom: 0.25rem;
        }

        .rse-qs-payment-pills {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .rse-qs-payment-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.25rem 0.625rem;
            border-radius: 0.375rem;
            background-color: var(--rse-surface);
            border: 1px solid var(--rse-border);
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--rse-text);
        }

        .rse-qs-aspect-group + .rse-qs-aspect-group {
            margin-top: 1rem;
            padding-top: 0.875rem;
            border-top: 1px solid var(--rse-border-subtle);
        }

        .rse-qs-aspect-subtitle {
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.025em;
            margin-bottom: 0.5rem;
        }

        .rse-qs-table-wrap {
            overflow-x: auto;
            border: 1px solid var(--rse-border);
            border-radius: 0.625rem;
            background-color: var(--rse-surface);
        }

        .rse-qs-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.875rem;
        }

        .rse-qs-table th {
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

        .rse-qs-table td {
            padding: 0.875rem 1rem;
            border-bottom: 1px solid var(--rse-border-subtle);
            vertical-align: top;
            color: var(--rse-text);
        }

        .rse-qs-table tbody tr:last-child td {
            border-bottom: none;
        }

        .rse-qs-table tbody tr:hover td {
            background-color: var(--rse-surface-muted);
        }

        .rse-qs-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.1875rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 700;
            border: 1px solid transparent;
            white-space: nowrap;
        }

        .rse-qs-badge-positive {
            background-color: var(--rse-emerald-bg);
            color: var(--rse-emerald-badge-text);
            border-color: var(--rse-emerald-border);
        }

        .rse-qs-badge-warning {
            background-color: var(--rse-amber-bg);
            color: var(--rse-amber-badge-text);
            border-color: var(--rse-amber-border);
        }

        .rse-qs-badge-low {
            background-color: var(--rse-rose-bg);
            color: var(--rse-rose-badge-text);
            border-color: var(--rse-rose-border);
        }

        .rse-qs-read-btn {
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

        .rse-qs-read-btn:hover {
            border-color: var(--rse-focus);
        }

        .rse-qs-empty {
            padding: 1.5rem 1rem;
            border-radius: 0.625rem;
            border: 1px dashed var(--rse-border);
            background-color: var(--rse-surface-muted);
            text-align: center;
            color: var(--rse-text-secondary);
            font-size: 0.875rem;
        }
    </style>

    <x-filament::section>
        <x-slot name="heading">Kuesioner Kepuasan Pasien</x-slot>
        <x-slot name="description">
            Hasil kuesioner per layanan. Setiap layanan memiliki ringkasan sendiri.
        </x-slot>

        <div class="rse-qs-root" x-data="{ tab: '{{ $groups[0]['type'] ?? 'igd' }}' }">
            <div class="rse-qs-topbar">
                <div class="rse-qs-summary-pill">
                    <span>Total keseluruhan: <strong>{{ $totalAllUnits }}</strong> pengisian dari {{ count($groups) }} unit layanan</span>
                </div>
                <a class="rse-qs-cta-link" href="{{ $indexUrl }}">
                    Lihat seluruh pengisian kuesioner
                </a>
            </div>

            <div class="rse-qs-tablist" role="tablist" aria-label="Pilih unit layanan kuesioner">
                @foreach ($groups as $group)
                    <button
                        type="button"
                        role="tab"
                        class="rse-qs-tab-btn"
                        :aria-selected="tab === '{{ $group['type'] }}' ? 'true' : 'false'"
                        @click="tab = '{{ $group['type'] }}'"
                    >
                        <span>{{ $group['label'] }} ({{ $group['total'] }})</span>
                        <span class="rse-qs-tab-meta">{{ $group['satisfaction'] }}% puas</span>
                    </button>
                @endforeach
            </div>

            @foreach ($groups as $group)
                @php
                    $groupTotal = (int) ($group['total'] ?? 0);
                    $satisfaction = (int) ($group['satisfaction'] ?? 0);
                    $byPayment = $group['by_payment'] ?? [];
                    $highestAspects = $group['aspects']['highest'] ?? [];
                    $lowestAspects = $group['aspects']['lowest'] ?? [];
                @endphp

                <div
                    x-show="tab === '{{ $group['type'] }}'"
                    x-cloak
                    role="tabpanel"
                    data-section="questionnaire-{{ $group['type'] }}"
                >
                    <div class="rse-qs-kpi-row">
                        <div class="rse-qs-kpi-card">
                            <p class="rse-qs-kpi-label">Total pengisian</p>
                            <p class="rse-qs-kpi-value">{{ $groupTotal }}</p>
                            <p class="rse-qs-kpi-note">Responden layanan {{ $group['label'] }}</p>
                        </div>

                        <div class="rse-qs-kpi-card">
                            <p class="rse-qs-kpi-label">Tingkat kepuasan</p>
                            <p class="rse-qs-kpi-value" style="color: var(--rse-emerald-text);">{{ $satisfaction }}%</p>
                            <div class="rse-qs-bar-track" aria-hidden="true">
                                <div style="width: {{ min(100, max(0, $satisfaction)) }}%; height: 100%; background-color: #059669;"></div>
                            </div>
                            <p class="rse-qs-kpi-note">Proporsi jawaban positif pada dua skala tertinggi</p>
                        </div>
                    </div>

                    <div class="rse-qs-analytics-grid">
                        <div class="rse-qs-panel">
                            <p class="rse-qs-panel-title">Berdasarkan kelompok usia</p>
                            @if (count($group['by_age']) === 0)
                                <p style="font-size: 0.875rem; color: var(--rse-text-secondary);">Belum ada pengisian.</p>
                            @else
                                <ul class="rse-qs-dist-list" style="list-style: none; padding: 0; margin: 0;">
                                    @foreach ($group['by_age'] as $age => $count)
                                        @php
                                            $agePct = $groupTotal > 0 ? (int) round(($count / $groupTotal) * 100) : 0;
                                        @endphp
                                        <li>
                                            <div class="rse-qs-dist-item-head">
                                                <span style="font-weight: 600; color: var(--rse-text);">{{ $age }}: {{ $count }}</span>
                                                <span style="color: var(--rse-text-secondary); font-weight: 600;">{{ $agePct }}%</span>
                                            </div>
                                            <div class="rse-qs-bar-track" style="margin-top: 0.25rem; height: 0.4375rem;">
                                                <div style="width: {{ $agePct }}%; height: 100%; background-color: #d97706;"></div>
                                            </div>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif

                            @if (count($byPayment) > 0)
                                <div style="margin-top: 1rem; padding-top: 0.875rem; border-top: 1px solid var(--rse-border-subtle);">
                                    <p style="font-size: 0.8125rem; font-weight: 700; color: var(--rse-text); margin-bottom: 0.375rem;">
                                        Distribusi cara bayar
                                    </p>
                                    <div class="rse-qs-payment-pills">
                                        @foreach ($byPayment as $paymentMethod => $paymentCount)
                                            @php
                                                $payPct = $groupTotal > 0 ? (int) round(($paymentCount / $groupTotal) * 100) : 0;
                                            @endphp
                                            <span class="rse-qs-payment-pill">
                                                <span>{{ $paymentMethod }}: {{ $paymentCount }}</span>
                                                <span style="color: var(--rse-text-secondary);">({{ $payPct }}%)</span>
                                            </span>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="rse-qs-panel">
                            <p class="rse-qs-panel-title">Evaluasi Aspek Layanan ({{ $group['label'] }})</p>
                            @if (count($highestAspects) === 0)
                                <p style="font-size: 0.875rem; color: var(--rse-text-secondary);">
                                    Belum ada data penilaian pertanyaan untuk dianalisis pada unit ini.
                                </p>
                            @else
                                <div class="rse-qs-aspect-group">
                                    <p class="rse-qs-aspect-subtitle" style="color: var(--rse-emerald-text);">
                                        Aspek Apresiasi Tertinggi
                                    </p>
                                    <div class="rse-qs-dist-list">
                                        @foreach ($highestAspects as $aspect)
                                            <div>
                                                <div class="rse-qs-dist-item-head">
                                                    <span style="font-weight: 600; color: var(--rse-text);">{{ $aspect['label'] }}</span>
                                                    <span style="font-weight: 700; color: var(--rse-emerald-text);">
                                                        {{ $aspect['percent'] }}% ({{ $aspect['positive'] }}/{{ $aspect['total'] }})
                                                    </span>
                                                </div>
                                                <div class="rse-qs-bar-track" style="margin-top: 0.25rem; height: 0.375rem;">
                                                    <div style="width: {{ $aspect['percent'] }}%; height: 100%; background-color: #059669;"></div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>

                                <div class="rse-qs-aspect-group">
                                    <p class="rse-qs-aspect-subtitle" style="color: var(--rse-amber-text);">
                                        Aspek Perlu Perhatian
                                    </p>
                                    <div class="rse-qs-dist-list">
                                        @foreach ($lowestAspects as $aspect)
                                            @php
                                                $isLow = $aspect['percent'] < 70;
                                                $barColor = $isLow ? '#e11d48' : '#d97706';
                                                $textColor = $isLow ? 'var(--rse-rose-text)' : 'var(--rse-amber-text)';
                                            @endphp
                                            <div>
                                                <div class="rse-qs-dist-item-head">
                                                    <span style="font-weight: 600; color: var(--rse-text);">{{ $aspect['label'] }}</span>
                                                    <span style="font-weight: 700; color: {{ $textColor }};">
                                                        {{ $aspect['percent'] }}% ({{ $aspect['positive'] }}/{{ $aspect['total'] }})
                                                    </span>
                                                </div>
                                                <div class="rse-qs-bar-track" style="margin-top: 0.25rem; height: 0.375rem;">
                                                    <div style="width: {{ $aspect['percent'] }}%; height: 100%; background-color: {{ $barColor }};"></div>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            @endif
                        </div>
                    </div>

                    <p style="font-size: 0.875rem; font-weight: 700; color: var(--rse-text); margin-bottom: 0.625rem;">
                        Pengisian terbaru
                    </p>
                    @if (count($group['latest']) === 0)
                        <div class="rse-qs-empty">
                            <p style="font-weight: 600; color: var(--rse-text);">Belum ada pengisian.</p>
                        </div>
                    @else
                        <div class="rse-qs-table-wrap">
                            <table class="rse-qs-table">
                                <thead>
                                    <tr>
                                        <th>Usia</th>
                                        <th>Jenis kelamin</th>
                                        <th>Cara bayar</th>
                                        <th>Skor Responden</th>
                                        <th>Saran Pasien</th>
                                        <th>Waktu</th>
                                        <th>Tindakan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($group['latest'] as $row)
                                        @php
                                            $scorePct = (int) ($row['score_percent'] ?? 0);
                                            $scorePos = (int) ($row['score_positive'] ?? 0);
                                            $scoreTot = (int) ($row['score_total'] ?? 0);
                                            $scoreBadgeClass = $scorePct >= 80
                                                ? 'rse-qs-badge-positive'
                                                : ($scorePct >= 60 ? 'rse-qs-badge-warning' : 'rse-qs-badge-low');
                                        @endphp
                                        <tr>
                                            <td style="white-space: nowrap; font-weight: 600;">{{ $row['age'] }}</td>
                                            <td style="white-space: nowrap;">{{ $row['gender'] }}</td>
                                            <td style="white-space: nowrap;">
                                                <span class="rse-qs-payment-pill" style="padding: 0.1875rem 0.5rem;">
                                                    {{ $row['payment'] }}
                                                </span>
                                            </td>
                                            <td style="white-space: nowrap;">
                                                @if ($scoreTot > 0)
                                                    <span class="rse-qs-badge {{ $scoreBadgeClass }}">
                                                        {{ $scorePct }}% ({{ $scorePos }}/{{ $scoreTot }} positif)
                                                    </span>
                                                @else
                                                    <span style="color: var(--rse-text-secondary); font-size: 0.8125rem;">Belum dinilai</span>
                                                @endif
                                            </td>
                                            <td style="max-width: 22rem; line-height: 1.45;">
                                                @if (! empty($row['suggestions']))
                                                    {{ \Illuminate\Support\Str::limit($row['suggestions'], 100) }}
                                                @else
                                                    <span style="color: var(--rse-text-secondary); font-size: 0.8125rem;">Tanpa catatan saran</span>
                                                @endif
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
                                                <a class="rse-qs-read-btn" href="{{ $row['url'] }}">
                                                    {{ $row['submitted'] }} : Baca jawaban
                                                </a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
