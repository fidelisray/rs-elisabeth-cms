<x-filament-widgets::widget>
    @php
        $tabKeys = array_values(array_map(static fn (array $t): string => $t['key'], $tabs));
        $defaultUnit = $questionnaireData['units'][0]['type'] ?? 'igd';
    @endphp

    <style>
        .rse-pv-root {
            --rse-surface: var(--rse-slate-surface, #ffffff);
            --rse-surface-muted: var(--rse-slate-muted, #f8fafc);
            --rse-border: var(--rse-slate-border, #cbd5e1);
            --rse-border-subtle: var(--rse-slate-divider, #e2e8f0);
            --rse-text: var(--rse-slate-text, #0f172a);
            --rse-text-secondary: var(--rse-slate-text-secondary, #475569);
            --rse-focus: var(--rse-brand-focus, #095988);

            background-color: var(--rse-surface);
            border: 1px solid var(--rse-border);
            border-radius: 0.75rem;
            padding: 1.25rem;
            color: var(--rse-text);
            font-variant-numeric: tabular-nums;
        }

        .dark .rse-pv-root {
            --rse-surface: var(--rse-slate-surface, #0f172a);
            --rse-surface-muted: var(--rse-slate-muted, #1e293b);
            --rse-border: var(--rse-slate-border, #334155);
            --rse-border-subtle: var(--rse-slate-divider, #233044);
            --rse-text: var(--rse-slate-text, #f8fafc);
            --rse-text-secondary: var(--rse-slate-text-secondary, #cbd5e1);
            --rse-focus: var(--rse-brand-focus, #1997e6);
        }

        .rse-pv-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .rse-pv-title {
            font-size: 1.0625rem;
            font-weight: 700;
            margin: 0;
            color: var(--rse-text);
        }

        .rse-pv-subtitle {
            font-size: 0.8125rem;
            color: var(--rse-text-secondary);
            margin: 0.125rem 0 0;
        }

        .rse-pv-tablist {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            border-bottom: 1px solid var(--rse-border-subtle);
            padding-bottom: 0.75rem;
            margin-bottom: 1rem;
        }

        .rse-pv-tab {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            min-height: 44px;
            padding: 0.5rem 0.9375rem;
            border-radius: 0.5rem;
            border: 1px solid var(--rse-border);
            background-color: var(--rse-surface-muted);
            color: var(--rse-text);
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 150ms ease, color 150ms ease, border-color 150ms ease;
        }

        .rse-pv-tab[aria-selected="true"] {
            background-color: var(--rse-brand-accent, #095988);
            border-color: var(--rse-brand-accent, #095988);
            color: var(--rse-brand-accent-text, #ffffff);
        }

        .dark .rse-pv-tab[aria-selected="true"] {
            background-color: var(--rse-brand-400, #1997e6);
            border-color: var(--rse-brand-400, #1997e6);
            color: #0f172a;
        }

        .rse-pv-tab:focus-visible,
        .rse-pv-cta:focus-visible,
        .rse-pv-link:focus-visible,
        .rse-pv-unit-btn:focus-visible {
            outline: 2px solid var(--rse-focus);
            outline-offset: 2px;
        }

        .rse-pv-tab-pill {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 0.125rem 0.4375rem;
            border-radius: 9999px;
            background-color: rgba(15, 23, 42, 0.08);
        }

        .dark .rse-pv-tab-pill {
            background-color: rgba(255, 255, 255, 0.12);
        }

        .rse-pv-tab[aria-selected="true"] .rse-pv-tab-pill {
            background-color: rgba(0, 0, 0, 0.24);
            color: #ffffff;
        }

        .dark .rse-pv-tab[aria-selected="true"] .rse-pv-tab-pill {
            background-color: rgba(15, 23, 42, 0.16);
            color: #0f172a;
        }

        .rse-pv-bar {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .rse-pv-stats {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .rse-pv-stat-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.375rem 0.6875rem;
            border-radius: 0.5rem;
            background-color: var(--rse-surface-muted);
            border: 1px solid var(--rse-border);
            font-size: 0.8125rem;
            color: var(--rse-text-secondary);
        }

        .rse-pv-stat-chip strong {
            color: var(--rse-text);
            font-weight: 700;
        }

        .rse-pv-cta {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 40px;
            padding: 0.375rem 0.875rem;
            border-radius: 0.5rem;
            background-color: var(--rse-brand-accent, #095988);
            color: var(--rse-brand-accent-text, #ffffff);
            font-size: 0.8125rem;
            font-weight: 600;
            text-decoration: none;
            transition: background-color 150ms ease;
        }

        .rse-pv-cta:hover {
            background-color: var(--rse-brand-accent-hover, #07476e);
        }

        .dark .rse-pv-cta {
            background-color: var(--rse-brand-400, #1997e6);
            color: #0f172a;
        }

        .rse-pv-cta--secondary {
            background-color: var(--rse-surface-muted);
            color: var(--rse-text);
            border: 1px solid var(--rse-border);
        }

        .rse-pv-cta--secondary:hover {
            border-color: var(--rse-brand-accent, #095988);
            background-color: var(--rse-surface-muted);
        }

        .rse-pv-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .rse-pv-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }

        .rse-pv-table th {
            text-align: left;
            padding: 0.625rem 0.75rem;
            border-bottom: 1px solid var(--rse-border);
            background-color: var(--rse-surface-muted);
            color: var(--rse-text-secondary);
            font-weight: 600;
            white-space: nowrap;
        }

        .rse-pv-table td {
            padding: 0.625rem 0.75rem;
            border-bottom: 1px solid var(--rse-border-subtle);
            color: var(--rse-text);
            vertical-align: middle;
        }

        .rse-pv-link {
            color: var(--rse-brand-accent, #095988);
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
        }

        .dark .rse-pv-link {
            color: var(--rse-brand-300, #52b5f7);
        }

        .rse-pv-link:hover {
            text-decoration: underline;
        }

        .rse-pv-status {
            display: inline-flex;
            padding: 0.1875rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .rse-pv-status--baru {
            background-color: #fef3c7;
            color: #92400e;
        }

        .dark .rse-pv-status--baru {
            background-color: #451a03;
            color: #fde68a;
        }

        .rse-pv-status--diproses {
            background-color: #e0f2fe;
            color: #075985;
        }

        .dark .rse-pv-status--diproses {
            background-color: #082f49;
            color: #bae6fd;
        }

        .rse-pv-status--selesai {
            background-color: #d1fae5;
            color: #065f46;
        }

        .dark .rse-pv-status--selesai {
            background-color: #064e3b;
            color: #a7f3d0;
        }

        .rse-pv-unit-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.625rem;
            margin-bottom: 1rem;
        }

        @media (min-width: 640px) {
            .rse-pv-unit-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        .rse-pv-unit-btn {
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            gap: 0.25rem;
            padding: 0.75rem 0.875rem;
            border-radius: 0.5rem;
            border: 1px solid var(--rse-border);
            background-color: var(--rse-surface-muted);
            color: var(--rse-text);
            cursor: pointer;
            text-align: left;
            min-height: 44px;
        }

        .rse-pv-unit-btn[aria-pressed="true"] {
            border-color: var(--rse-brand-accent, #095988);
            box-shadow: inset 0 0 0 1px var(--rse-brand-accent, #095988);
        }

        .dark .rse-pv-unit-btn[aria-pressed="true"] {
            border-color: var(--rse-brand-400, #1997e6);
            box-shadow: inset 0 0 0 1px var(--rse-brand-400, #1997e6);
        }

        .rse-pv-aspects-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        @media (min-width: 768px) {
            .rse-pv-aspects-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .rse-pv-aspect-box {
            border: 1px solid var(--rse-border-subtle);
            border-radius: 0.5rem;
            padding: 0.75rem;
            background-color: var(--rse-surface-muted);
        }

        .rse-pv-aspect-heading {
            font-size: 0.78125rem;
            font-weight: 700;
            margin: 0 0 0.5rem;
            color: var(--rse-text);
        }

        .rse-pv-aspect-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
            font-size: 0.78125rem;
        }

        .rse-pv-aspect-item {
            display: flex;
            justify-content: space-between;
            gap: 0.75rem;
            color: var(--rse-text-secondary);
        }

        .rse-pv-aspect-item strong {
            color: var(--rse-text);
            white-space: nowrap;
        }

        .rse-pv-two-col {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        @media (min-width: 1024px) {
            .rse-pv-two-col {
                grid-template-columns: minmax(0, 1fr) minmax(0, 1.4fr);
            }
        }

        .rse-pv-empty {
            padding: 1.25rem;
            border-radius: 0.5rem;
            background-color: var(--rse-surface-muted);
            border: 1px dashed var(--rse-border);
            text-align: center;
            font-size: 0.875rem;
            color: var(--rse-text-secondary);
        }
    </style>

    @if ($tabs !== [])
        <div
            class="rse-pv-root"
            data-rse-patient-voice-workspace
            x-data="{
                activeTab: @js($defaultTab),
                activeUnit: @js($defaultUnit),
                tabKeys: @js($tabKeys),
                moveTab(step) {
                    const idx = this.tabKeys.indexOf(this.activeTab);
                    if (idx === -1) return;
                    const nextIdx = (idx + step + this.tabKeys.length) % this.tabKeys.length;
                    this.activeTab = this.tabKeys[nextIdx];
                    this.$nextTick(() => {
                        this.$el.querySelector(`[data-rse-pv-tab='${this.activeTab}']`)?.focus();
                    });
                }
            }"
        >
            <div class="rse-pv-header">
                <div>
                    <h2 class="rse-pv-title">{{ __('Suara Pasien & Survei') }}</h2>
                    <p class="rse-pv-subtitle">{{ __('Ringkasan terpadu masukan pasien, kuesioner kepuasan, dan formulir kustom (tanpa PII).') }}</p>
                </div>
            </div>

            <div class="rse-pv-tablist" role="tablist" aria-label="{{ __('Kanal Suara Pasien') }}">
                @foreach ($tabs as $tab)
                    <button
                        type="button"
                        role="tab"
                        id="rse-pv-tab-{{ $tab['key'] }}"
                        data-rse-pv-tab="{{ $tab['key'] }}"
                        aria-controls="rse-pv-panel-{{ $tab['key'] }}"
                        x-bind:aria-selected="activeTab === @js($tab['key']) ? 'true' : 'false'"
                        x-bind:tabindex="activeTab === @js($tab['key']) ? 0 : -1"
                        x-on:click="activeTab = @js($tab['key'])"
                        x-on:keydown.arrow-right.prevent="moveTab(1)"
                        x-on:keydown.arrow-left.prevent="moveTab(-1)"
                        class="rse-pv-tab"
                    >
                        <span>{{ $tab['label'] }}</span>
                        <span class="rse-pv-tab-pill">{{ $tab['count_label'] }}</span>
                    </button>
                @endforeach
            </div>

            {{-- TAB 1: Kritik & Saran --}}
            @if ($feedbackData !== null)
                <div
                    role="tabpanel"
                    id="rse-pv-panel-feedback"
                    aria-labelledby="rse-pv-tab-feedback"
                    x-show="activeTab === 'feedback'"
                    x-cloak
                >
                    <div class="rse-pv-bar">
                        <div class="rse-pv-stats">
                            <span class="rse-pv-stat-chip">{{ __('Total') }}: <strong>{{ $feedbackData['summary']['total'] }}</strong></span>
                            <span class="rse-pv-stat-chip">{{ __('Baru') }}: <strong>{{ $feedbackData['summary']['baru'] }}</strong></span>
                            <span class="rse-pv-stat-chip">{{ __('Diproses') }}: <strong>{{ $feedbackData['summary']['diproses'] }}</strong></span>
                            <span class="rse-pv-stat-chip">{{ __('Selesai') }}: <strong>{{ $feedbackData['summary']['selesai'] }}</strong></span>
                        </div>
                        <a href="{{ $feedbackData['index_url'] }}" class="rse-pv-cta">
                            {{ __('Lihat Semua') }}
                        </a>
                    </div>

                    @if ($feedbackData['latest'] === [])
                        <div class="rse-pv-empty">{{ __('Belum ada kritik dan saran masuk.') }}</div>
                    @else
                        <div class="rse-pv-table-wrap">
                            <table class="rse-pv-table">
                                <thead>
                                    <tr>
                                        <th scope="col">{{ __('Tiket') }}</th>
                                        <th scope="col">{{ __('Kategori') }}</th>
                                        <th scope="col">{{ __('Ringkasan Keluhan') }}</th>
                                        <th scope="col">{{ __('Status') }}</th>
                                        <th scope="col">{{ __('Dikirim') }}</th>
                                        <th scope="col">{{ __('Aksi') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($feedbackData['latest'] as $row)
                                        <tr>
                                            <td style="white-space: nowrap;"><strong>{{ $row['ticket'] }}</strong></td>
                                            <td>{{ $row['categories'] }}</td>
                                            <td>{{ $row['detail'] }}</td>
                                            <td>
                                                <span class="rse-pv-status rse-pv-status--{{ $row['status'] }}">
                                                    {{ $row['status_label'] }}
                                                </span>
                                            </td>
                                            <td style="white-space: nowrap;">{{ $row['submitted'] }}</td>
                                            <td>
                                                <a href="{{ $row['url'] }}" class="rse-pv-link">{{ __('Lihat') }}</a>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>
            @endif

            {{-- TAB 2: Kuesioner Kepuasan --}}
            @if ($questionnaireData !== null)
                <div
                    role="tabpanel"
                    id="rse-pv-panel-questionnaires"
                    aria-labelledby="rse-pv-tab-questionnaires"
                    x-show="activeTab === 'questionnaires'"
                    x-cloak
                >
                    <div class="rse-pv-bar">
                        <div class="rse-pv-stats">
                            <span class="rse-pv-stat-chip">
                                {{ __('Total Kuesioner Masuk') }}: <strong>{{ $questionnaireData['total'] }}</strong>
                            </span>
                        </div>
                        <a href="{{ $questionnaireData['index_url'] }}" class="rse-pv-cta">
                            {{ __('Lihat Semua') }}
                        </a>
                    </div>

                    <div class="rse-pv-unit-grid">
                        @foreach ($questionnaireData['units'] as $unit)
                            <button
                                type="button"
                                class="rse-pv-unit-btn"
                                data-rse-pv-unit="{{ $unit['type'] }}"
                                x-bind:aria-pressed="activeUnit === @js($unit['type']) ? 'true' : 'false'"
                                x-on:click="activeUnit = @js($unit['type'])"
                            >
                                <span style="font-weight: 700; font-size: 0.875rem;">{{ $unit['label'] }}</span>
                                <span style="font-size: 0.8125rem; color: var(--rse-text-secondary);">
                                    {{ __(':percent% positif · :count respons', ['percent' => $unit['satisfaction'], 'count' => $unit['total']]) }}
                                </span>
                            </button>
                        @endforeach
                    </div>

                    @foreach ($questionnaireData['units'] as $unit)
                        <div x-show="activeUnit === @js($unit['type'])" x-cloak>
                            @if ($unit['total'] === 0)
                                <div class="rse-pv-empty">
                                    {{ __('Belum ada respons kuesioner untuk unit :unit.', ['unit' => $unit['label']]) }}
                                </div>
                            @else
                                <div class="rse-pv-aspects-grid">
                                    <div class="rse-pv-aspect-box">
                                        <h3 class="rse-pv-aspect-heading">{{ __('3 Aspek Tertinggi') }}</h3>
                                        <ul class="rse-pv-aspect-list">
                                            @foreach ($unit['highest'] as $aspect)
                                                <li class="rse-pv-aspect-item">
                                                    <span>{{ $aspect['label'] }}</span>
                                                    <strong>{{ $aspect['percent'] }}%</strong>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                    <div class="rse-pv-aspect-box">
                                        <h3 class="rse-pv-aspect-heading">{{ __('3 Aspek Perlu Perhatian') }}</h3>
                                        <ul class="rse-pv-aspect-list">
                                            @foreach ($unit['lowest'] as $aspect)
                                                <li class="rse-pv-aspect-item">
                                                    <span>{{ $aspect['label'] }}</span>
                                                    <strong>{{ $aspect['percent'] }}% ({{ $aspect['negative'] }}/{{ $aspect['total'] }})</strong>
                                                </li>
                                            @endforeach
                                        </ul>
                                    </div>
                                </div>

                                <div class="rse-pv-table-wrap">
                                    <table class="rse-pv-table">
                                        <thead>
                                            <tr>
                                                <th scope="col">{{ __('Skor Positif') }}</th>
                                                <th scope="col">{{ __('Kelompok Usia') }}</th>
                                                <th scope="col">{{ __('Cara Bayar') }}</th>
                                                <th scope="col">{{ __('Saran') }}</th>
                                                <th scope="col">{{ __('Dikirim') }}</th>
                                                <th scope="col">{{ __('Aksi') }}</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($unit['latest'] as $row)
                                                <tr>
                                                    <td><strong>{{ $row['score_percent'] }}%</strong></td>
                                                    <td>{{ $row['age'] }}</td>
                                                    <td>{{ $row['payment'] }}</td>
                                                    <td>{{ $row['suggestions'] }}</td>
                                                    <td style="white-space: nowrap;">{{ $row['submitted'] }}</td>
                                                    <td>
                                                        <a href="{{ $row['url'] }}" class="rse-pv-link">{{ __('Lihat') }}</a>
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
            @endif

            {{-- TAB 3: Formulir Kustom --}}
            @if ($dynamicData !== null)
                <div
                    role="tabpanel"
                    id="rse-pv-panel-dynamic_forms"
                    aria-labelledby="rse-pv-tab-dynamic_forms"
                    x-show="activeTab === 'dynamic_forms'"
                    x-cloak
                >
                    <div class="rse-pv-bar">
                        <div class="rse-pv-stats">
                            @if ($dynamicData['can_view_forms'])
                                <span class="rse-pv-stat-chip">
                                    {{ __('Formulir Terpublikasi') }}: <strong>{{ $dynamicData['active_forms_count'] }}</strong>
                                </span>
                            @endif
                            @if ($dynamicData['can_view_submissions'])
                                <span class="rse-pv-stat-chip">
                                    {{ __('Total Respons Masuk') }}: <strong>{{ $dynamicData['total_submissions_count'] }}</strong>
                                </span>
                            @endif
                        </div>
                        <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                            @if ($dynamicData['forms_index_url'])
                                <a href="{{ $dynamicData['forms_index_url'] }}" class="rse-pv-cta rse-pv-cta--secondary">
                                    {{ __('Kelola Formulir') }}
                                </a>
                            @endif
                            @if ($dynamicData['submissions_index_url'])
                                <a href="{{ $dynamicData['submissions_index_url'] }}" class="rse-pv-cta">
                                    {{ __('Lihat Semua') }}
                                </a>
                            @endif
                        </div>
                    </div>

                    <div class="rse-pv-two-col">
                        @if ($dynamicData['can_view_forms'])
                            <div>
                                <h3 class="rse-pv-aspect-heading">{{ __('Formulir Aktif Terbaru') }}</h3>
                                @if ($dynamicData['active_forms'] === [])
                                    <div class="rse-pv-empty">{{ __('Belum ada formulir kustom yang dipublikasikan.') }}</div>
                                @else
                                    <div class="rse-pv-table-wrap">
                                        <table class="rse-pv-table">
                                            <thead>
                                                <tr>
                                                    <th scope="col">{{ __('Judul') }}</th>
                                                    <th scope="col">{{ __('Respons') }}</th>
                                                    <th scope="col">{{ __('Aksi') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($dynamicData['active_forms'] as $form)
                                                    <tr>
                                                        <td>
                                                            <strong>{{ $form['title'] }}</strong>
                                                            <div style="font-size: 0.75rem; color: var(--rse-text-secondary);">{{ $form['mode'] }}</div>
                                                        </td>
                                                        <td>{{ $form['submissions_count'] }}</td>
                                                        <td>
                                                            <a href="{{ $form['analytics_url'] }}" class="rse-pv-link">{{ __('Analitik') }}</a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        @endif

                        @if ($dynamicData['can_view_submissions'])
                            <div>
                                <h3 class="rse-pv-aspect-heading">{{ __('Respons Formulir Terbaru') }}</h3>
                                @if ($dynamicData['latest_submissions'] === [])
                                    <div class="rse-pv-empty">{{ __('Belum ada respons formulir kustom masuk.') }}</div>
                                @else
                                    <div class="rse-pv-table-wrap">
                                        <table class="rse-pv-table">
                                            <thead>
                                                <tr>
                                                    <th scope="col">{{ __('Respons') }}</th>
                                                    <th scope="col">{{ __('Formulir') }}</th>
                                                    <th scope="col">{{ __('Dikirim') }}</th>
                                                    <th scope="col">{{ __('Aksi') }}</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($dynamicData['latest_submissions'] as $sub)
                                                    <tr>
                                                        <td style="white-space: nowrap;"><strong>{{ $sub['response_label'] }}</strong></td>
                                                        <td>
                                                            {{ $sub['form_title'] }}
                                                            <div style="font-size: 0.75rem; color: var(--rse-text-secondary);">{{ $sub['mode_label'] }}</div>
                                                        </td>
                                                        <td style="white-space: nowrap;">{{ $sub['submitted_at'] }}</td>
                                                        <td>
                                                            <a href="{{ $sub['view_url'] }}" class="rse-pv-link">{{ __('Lihat') }}</a>
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>
                                @endif
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    @endif
</x-filament-widgets::widget>
