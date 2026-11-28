<x-filament-widgets::widget>
    <style>
        .rse-kpi-grid {
            --rse-surface: var(--rse-slate-surface, #ffffff);
            --rse-surface-muted: var(--rse-slate-muted, #f8fafc);
            --rse-border: var(--rse-slate-border, #cbd5e1);
            --rse-border-subtle: var(--rse-slate-divider, #e2e8f0);
            --rse-text: var(--rse-slate-text, #0f172a);
            --rse-text-secondary: var(--rse-slate-text-secondary, #475569);
            --rse-focus: var(--rse-brand-focus, #095988);

            display: grid;
            grid-template-columns: 1fr;
            gap: 0.875rem;
            font-variant-numeric: tabular-nums;
        }

        .dark .rse-kpi-grid {
            --rse-surface: var(--rse-slate-surface, #0f172a);
            --rse-surface-muted: var(--rse-slate-muted, #1e293b);
            --rse-border: var(--rse-slate-border, #334155);
            --rse-border-subtle: var(--rse-slate-divider, #233044);
            --rse-text: var(--rse-slate-text, #f8fafc);
            --rse-text-secondary: var(--rse-slate-text-secondary, #cbd5e1);
            --rse-focus: var(--rse-brand-focus, #1997e6);
        }

        @media (min-width: 640px) {
            .rse-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1280px) {
            .rse-kpi-grid {
                grid-template-columns: repeat(auto-fit, minmax(210px, 1fr));
            }
        }

        .rse-kpi-card {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            gap: 0.625rem;
            padding: 1rem 1.125rem;
            border-radius: 0.75rem;
            border: 1px solid var(--rse-border);
            background-color: var(--rse-surface);
            text-decoration: none;
            color: var(--rse-text);
            transition: border-color 150ms ease, box-shadow 150ms ease, transform 150ms ease;
        }

        .rse-kpi-card:hover {
            border-color: var(--rse-brand-accent, #095988);
            box-shadow: 0 4px 12px rgba(9, 89, 136, 0.08);
            transform: translateY(-1px);
        }

        .dark .rse-kpi-card:hover {
            border-color: var(--rse-brand-400, #1997e6);
            box-shadow: 0 4px 12px rgba(25, 151, 230, 0.14);
        }

        .rse-kpi-card:focus-visible {
            outline: 2px solid var(--rse-focus);
            outline-offset: 2px;
        }

        .rse-kpi-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
        }

        .rse-kpi-label {
            font-size: 0.78125rem;
            font-weight: 600;
            color: var(--rse-text-secondary);
            line-height: 1.3;
        }

        .rse-kpi-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.1875rem 0.5rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .rse-kpi-badge--success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .dark .rse-kpi-badge--success {
            background-color: #064e3b;
            color: #a7f3d0;
        }

        .rse-kpi-badge--warning {
            background-color: #fef3c7;
            color: #92400e;
        }

        .dark .rse-kpi-badge--warning {
            background-color: #451a03;
            color: #fde68a;
        }

        .rse-kpi-badge--danger {
            background-color: #ffe4e6;
            color: #9f1239;
        }

        .dark .rse-kpi-badge--danger {
            background-color: #4c0519;
            color: #fecdd3;
        }

        .rse-kpi-badge--info {
            background-color: #e0f2fe;
            color: #075985;
        }

        .dark .rse-kpi-badge--info {
            background-color: #082f49;
            color: #bae6fd;
        }

        .rse-kpi-badge--neutral {
            background-color: var(--rse-surface-muted);
            color: var(--rse-text-secondary);
            border: 1px solid var(--rse-border);
        }

        .rse-kpi-value {
            font-size: 1.625rem;
            font-weight: 800;
            line-height: 1.15;
            color: var(--rse-text);
            letter-spacing: -0.02em;
        }

        .rse-kpi-foot {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            padding-top: 0.5rem;
            border-top: 1px solid var(--rse-border-subtle);
            font-size: 0.75rem;
        }

        .rse-kpi-secondary {
            color: var(--rse-text-secondary);
        }

        .rse-kpi-cta {
            color: var(--rse-brand-accent, #095988);
            font-weight: 600;
            white-space: nowrap;
        }

        .dark .rse-kpi-cta {
            color: var(--rse-brand-300, #52b5f7);
        }
    </style>

    <section aria-label="{{ __('Ringkasan KPI Operasional') }}" data-rse-dashboard-kpi-strip>
        <div class="rse-kpi-grid">
            @foreach ($cards as $card)
                <a
                    href="{{ $card['url'] }}"
                    class="rse-kpi-card"
                    data-rse-kpi-card="{{ $card['key'] }}"
                >
                    <div class="rse-kpi-head">
                        <span class="rse-kpi-label">{{ $card['label'] }}</span>
                        <span class="rse-kpi-badge rse-kpi-badge--{{ $card['tone'] }}">
                            {{ $card['badge'] }}
                        </span>
                    </div>

                    <div class="rse-kpi-value">{{ $card['value'] }}</div>

                    <div class="rse-kpi-foot">
                        <span class="rse-kpi-secondary">{{ $card['secondary'] }}</span>
                        <span class="rse-kpi-cta">{{ $card['cta'] }} &rarr;</span>
                    </div>
                </a>
            @endforeach
        </div>
    </section>
</x-filament-widgets::widget>
