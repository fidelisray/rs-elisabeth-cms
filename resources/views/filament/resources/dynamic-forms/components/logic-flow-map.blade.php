<div class="rse-fm-root" data-rse-logic-flow-map>
    <style>
        .rse-fm-root {
            --rse-fm-surface: var(--rse-slate-surface, #ffffff);
            --rse-fm-muted: var(--rse-slate-muted, #f8fafc);
            --rse-fm-border: var(--rse-slate-border, #cbd5e1);
            --rse-fm-divider: var(--rse-slate-divider, #e2e8f0);
            --rse-fm-text: var(--rse-slate-text, #0f172a);
            --rse-fm-text-secondary: var(--rse-slate-text-secondary, #475569);
            color: var(--rse-fm-text);
            font-variant-numeric: tabular-nums;
        }

        .dark .rse-fm-root {
            --rse-fm-surface: var(--rse-slate-surface, #0f172a);
            --rse-fm-muted: var(--rse-slate-muted, #1e293b);
            --rse-fm-border: var(--rse-slate-border, #334155);
            --rse-fm-divider: var(--rse-slate-divider, #233044);
            --rse-fm-text: var(--rse-slate-text, #f8fafc);
            --rse-fm-text-secondary: var(--rse-slate-text-secondary, #cbd5e1);
        }

        .rse-fm-list {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .rse-fm-card {
            padding: 0.875rem 1rem;
            border-radius: 0.625rem;
            background-color: var(--rse-fm-muted);
            border: 1px solid var(--rse-fm-border);
        }

        .rse-fm-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--rse-fm-divider);
        }

        .rse-fm-title {
            font-size: 0.875rem;
            font-weight: 700;
            margin: 0;
        }

        .rse-fm-badges {
            display: flex;
            flex-wrap: wrap;
            gap: 0.375rem;
        }

        .rse-fm-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.125rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .rse-fm-badge-primary {
            background-color: var(--rse-brand-badge-bg, #dceff9);
            color: var(--rse-brand-badge-text, #07476e);
        }

        .rse-fm-badge-neutral {
            background-color: var(--rse-fm-surface);
            border: 1px solid var(--rse-fm-border);
            color: var(--rse-fm-text-secondary);
        }

        .rse-fm-branches {
            display: flex;
            flex-direction: column;
            gap: 0.375rem;
            margin: 0 0 0.5rem;
            padding: 0;
            list-style: none;
        }

        .rse-fm-branch {
            padding: 0.375rem 0.625rem;
            border-radius: 0.375rem;
            background-color: var(--rse-fm-surface);
            border-left: 3px solid var(--rse-brand-secondary, #159e74);
            font-size: 0.8125rem;
            line-height: 1.4;
        }

        .rse-fm-default {
            font-size: 0.8125rem;
            color: var(--rse-fm-text-secondary);
            margin: 0;
        }

        .rse-fm-default strong {
            color: var(--rse-fm-text);
        }

        .rse-fm-warn {
            margin-top: 0.5rem;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            background-color: #ffe4e6;
            color: #9f1239;
            font-size: 0.75rem;
            font-weight: 600;
            line-height: 1.45;
        }

        .dark .rse-fm-warn {
            background-color: #4c0519;
            color: #fecdd3;
        }

        .rse-fm-empty {
            padding: 1rem;
            border-radius: 0.5rem;
            border: 1px dashed var(--rse-fm-border);
            background-color: var(--rse-fm-muted);
            font-size: 0.8125rem;
            color: var(--rse-fm-text-secondary);
            text-align: center;
        }
    </style>

    @if ($flowSections === [])
        <div class="rse-fm-empty">
            {{ __('Belum ada Bagian pada formulir ini. Tambahkan Bagian dan pertanyaan di Langkah 2 terlebih dahulu.') }}
        </div>
    @else
        <div class="rse-fm-list">
            @foreach ($flowSections as $sec)
                <div class="rse-fm-card">
                    <div class="rse-fm-head">
                        <p class="rse-fm-title">{{ $sec['label'] }}</p>
                        <div class="rse-fm-badges">
                            <span class="rse-fm-badge rse-fm-badge-neutral">
                                {{ __(':count pertanyaan', ['count' => $sec['field_count']]) }}
                            </span>
                            @if ($sec['visibility_count'] > 0)
                                <span class="rse-fm-badge rse-fm-badge-primary">
                                    {{ __(':count bersyarat (Menu 1)', ['count' => $sec['visibility_count']]) }}
                                </span>
                            @endif
                        </div>
                    </div>

                    @if ($sec['choice_branches'] !== [])
                        <ul class="rse-fm-branches">
                            @foreach ($sec['choice_branches'] as $branch)
                                <li class="rse-fm-branch">
                                    <strong>{{ __('Jika') }}</strong> "{{ $branch['field_label'] }}" = "{{ $branch['choice_label'] }}"
                                    &rarr; <strong>{{ $branch['target_label'] }}</strong>
                                </li>
                            @endforeach
                        </ul>
                    @endif

                    <p class="rse-fm-default">
                        {{ __('Tujuan default akhir bagian:') }} <strong>{{ $sec['default_next_label'] }}</strong>
                    </p>

                    @foreach ($sec['warnings'] as $warning)
                        <div class="rse-fm-warn" role="alert">
                            {{ $warning }}
                        </div>
                    @endforeach
                </div>
            @endforeach
        </div>
    @endif
</div>
