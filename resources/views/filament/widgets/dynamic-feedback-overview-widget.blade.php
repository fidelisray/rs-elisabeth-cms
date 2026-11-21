<x-filament-widgets::widget>
    <div class="rse-dfw-root">
        <style>
            .rse-dfw-root {
                --rse-w-surface: #ffffff;
                --rse-w-muted: #f8fafc;
                --rse-w-border: #64748b;
                --rse-w-divider: #cbd5e1;
                --rse-w-text: #0f172a;
                --rse-w-text-secondary: #475569;
                --rse-w-accent: #b45309;
                --rse-w-accent-text: #ffffff;
                --rse-w-badge-bg: #fef3c7;
                --rse-w-badge-text: #92400e;
                color: var(--rse-w-text);
                font-variant-numeric: tabular-nums;
            }

            .dark .rse-dfw-root {
                --rse-w-surface: #18181b;
                --rse-w-muted: #0f172a;
                --rse-w-border: #71717a;
                --rse-w-divider: #3f3f46;
                --rse-w-text: #f8fafc;
                --rse-w-text-secondary: #cbd5e1;
                --rse-w-accent: #fbbf24;
                --rse-w-accent-text: #0f172a;
                --rse-w-badge-bg: #451a03;
                --rse-w-badge-text: #fde68a;
            }

            .rse-dfw-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 1rem;
            }

            @media (min-width: 900px) {
                .rse-dfw-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            .rse-dfw-box {
                padding: 1rem;
                border-radius: 0.625rem;
                background-color: var(--rse-w-muted);
                border: 1px solid var(--rse-w-divider);
            }

            .rse-dfw-cta {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 44px;
                padding: 0.5rem 1rem;
                border-radius: 0.5rem;
                background-color: var(--rse-w-accent);
                color: var(--rse-w-accent-text);
                font-size: 0.875rem;
                font-weight: 600;
                text-decoration: none;
            }

            .rse-dfw-link {
                display: inline-flex;
                align-items: center;
                min-height: 44px;
                color: var(--rse-w-accent);
                font-weight: 600;
                font-size: 0.875rem;
                text-decoration: underline;
                text-underline-offset: 2px;
            }

            .rse-dfw-cta:focus-visible,
            .rse-dfw-link:focus-visible {
                outline: 2px solid var(--rse-w-accent);
                outline-offset: 2px;
            }
        </style>

        <x-filament::section>
            <x-slot name="heading">Custom Questionnaire Form & Respons Terbaru</x-slot>
            <x-slot name="description">
                Ringkasan formulir kustom terpublikasi dan daftar respons terbaru yang masuk.
            </x-slot>

            <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.75rem; margin-bottom: 1rem;">
                <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
                    @if ($canViewForms)
                        <span style="padding: 0.375rem 0.75rem; border-radius: 0.375rem; background-color: var(--rse-w-muted); border: 1px solid var(--rse-w-divider); font-size: 0.8125rem; font-weight: 600;">
                            Formulir Aktif: {{ $activeFormsCount }}
                        </span>
                    @endif
                    @if ($canViewSubmissions)
                        <span style="padding: 0.375rem 0.75rem; border-radius: 0.375rem; background-color: var(--rse-w-badge-bg); color: var(--rse-w-badge-text); font-size: 0.8125rem; font-weight: 700;">
                            Total Respons Masuk: {{ $totalSubmissionsCount }}
                        </span>
                    @endif
                </div>
                <div style="display: flex; flex-wrap: wrap; gap: 0.5rem;">
                    @if ($formsIndexUrl)
                        <a href="{{ $formsIndexUrl }}" class="rse-dfw-cta">Kelola Custom Questionnaire Form</a>
                    @endif
                    @if ($submissionsIndexUrl)
                        <a href="{{ $submissionsIndexUrl }}" class="rse-dfw-link">Semua Custom Questionnaire Responses</a>
                    @endif
                </div>
            </div>

            <div class="rse-dfw-grid">
                @if ($canViewForms)
                    <div class="rse-dfw-box">
                        <h3 style="font-size: 0.875rem; font-weight: 700; margin: 0 0 0.75rem;">
                            Formulir Kustom Terpublikasi
                        </h3>
                        @if ($activeForms === [])
                            <p style="font-size: 0.8125rem; color: var(--rse-w-text-secondary); margin: 0;">
                                Belum ada formulir kustom yang dipublikasikan. Buat dan publikasikan formulir di menu Custom Questionnaire Form.
                            </p>
                        @else
                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                @foreach ($activeForms as $formItem)
                                    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.5rem 0.75rem; border-radius: 0.375rem; background-color: var(--rse-w-surface); border: 1px solid var(--rse-w-divider);">
                                        <div style="min-width: 0; flex: 1 1 12rem;">
                                            <p style="font-size: 0.875rem; font-weight: 600; margin: 0; overflow-wrap: anywhere;">{{ $formItem['title'] }}</p>
                                            <p style="font-size: 0.75rem; color: var(--rse-w-text-secondary); margin: 0;">
                                                {{ $formItem['mode'] }} · {{ $formItem['submissions_count'] }} respons
                                            </p>
                                        </div>
                                        <a href="{{ $formItem['analytics_url'] }}" class="rse-dfw-link">Analitik</a>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif

                @if ($canViewSubmissions)
                    <div class="rse-dfw-box">
                        <h3 style="font-size: 0.875rem; font-weight: 700; margin: 0 0 0.75rem;">
                            Respons Terbaru Masuk
                        </h3>
                        @if ($latestSubmissions === [])
                            <p style="font-size: 0.8125rem; color: var(--rse-w-text-secondary); margin: 0;">
                                Belum ada respons yang masuk saat ini.
                            </p>
                        @else
                            <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                @foreach ($latestSubmissions as $item)
                                    <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.5rem 0.75rem; border-radius: 0.375rem; background-color: var(--rse-w-surface); border: 1px solid var(--rse-w-divider);">
                                        <div style="min-width: 0; flex: 1 1 12rem;">
                                            <p style="font-size: 0.875rem; font-weight: 700; margin: 0;">{{ $item['response_label'] }}</p>
                                            <p style="font-size: 0.75rem; color: var(--rse-w-text-secondary); margin: 0; overflow-wrap: anywhere;">
                                                {{ $item['form_title'] }} ({{ $item['mode_label'] }}) · {{ $item['submitted_at'] }}
                                            </p>
                                        </div>
                                        <a href="{{ $item['view_url'] }}" class="rse-dfw-link">Lihat</a>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </x-filament::section>
    </div>
</x-filament-widgets::widget>
