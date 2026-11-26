<x-filament-widgets::widget>
    <div class="rse-dfw-root">
        <style>
            .rse-dfw-root {
                --rse-w-surface: var(--rse-slate-surface, #ffffff);
                --rse-w-muted: var(--rse-slate-muted, #f8fafc);
                --rse-w-border: var(--rse-slate-border-strong, #64748b);
                --rse-w-divider: var(--rse-slate-border, #cbd5e1);
                --rse-w-text: var(--rse-slate-text, #0f172a);
                --rse-w-text-secondary: var(--rse-slate-text-secondary, #475569);
                --rse-w-accent: var(--rse-brand-accent, #095988);
                --rse-w-accent-hover: var(--rse-brand-accent-hover, #07476e);
                --rse-w-accent-text: var(--rse-brand-accent-text, #ffffff);
                --rse-w-link: var(--rse-brand-link, #095988);
                --rse-w-badge-bg: var(--rse-brand-badge-bg, #dceff9);
                --rse-w-badge-text: var(--rse-brand-badge-text, #07476e);
                color: var(--rse-w-text);
                font-variant-numeric: tabular-nums;
            }

            .dark .rse-dfw-root {
                --rse-w-surface: var(--rse-slate-surface, #0f172a);
                --rse-w-muted: var(--rse-slate-muted, #1e293b);
                --rse-w-border: var(--rse-slate-border-strong, #64748b);
                --rse-w-divider: var(--rse-slate-border, #334155);
                --rse-w-text: var(--rse-slate-text, #f8fafc);
                --rse-w-text-secondary: var(--rse-slate-text-secondary, #cbd5e1);
                --rse-w-accent: var(--rse-brand-accent, #1997e6);
                --rse-w-accent-hover: var(--rse-brand-accent-hover, #89c9ea);
                --rse-w-accent-text: var(--rse-brand-accent-text, #0b0f19);
                --rse-w-link: var(--rse-brand-link, #38bdf8);
                --rse-w-badge-bg: var(--rse-brand-badge-bg, #052d47);
                --rse-w-badge-text: var(--rse-brand-badge-text, #bae0f4);
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
                transition: background-color 150ms ease;
            }

            .rse-dfw-cta:hover {
                background-color: var(--rse-w-accent-hover);
            }

            .rse-dfw-link {
                display: inline-flex;
                align-items: center;
                min-height: 44px;
                color: var(--rse-w-link);
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
