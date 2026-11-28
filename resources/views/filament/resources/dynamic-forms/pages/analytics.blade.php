<x-filament-panels::page>
    @php
        $summary = $analytics['summary'] ?? [];
        $questions = $analytics['questions'] ?? [];
        $totalResponses = (int) ($summary['total_submissions'] ?? 0);
        $lastSubmittedAt = $summary['last_submitted_at'] ?? null;
        $avgFillRate = $summary['avg_fill_rate'] ?? null;
    @endphp

    <div class="rse-dfa-root">
        <style>
            .rse-dfa-root {
                --rse-a-surface: var(--rse-slate-surface, #ffffff);
                --rse-a-muted: var(--rse-slate-muted, #f8fafc);
                --rse-a-border: var(--rse-slate-border-strong, #64748b);
                --rse-a-divider: var(--rse-slate-border, #cbd5e1);
                --rse-a-text: var(--rse-slate-text, #0f172a);
                --rse-a-text-secondary: var(--rse-slate-text-secondary, #475569);
                --rse-a-accent: var(--rse-brand-accent, #095988);
                --rse-a-accent-text: var(--rse-brand-accent-text, #ffffff);
                --rse-a-link: var(--rse-brand-link, #095988);
                --rse-a-track: var(--rse-slate-track, #e2e8f0);
                --rse-a-badge-bg: var(--rse-brand-badge-bg, #dceff9);
                --rse-a-badge-text: var(--rse-brand-badge-text, #07476e);
                --rse-a-err-bg: #ffe4e6;
                --rse-a-err-text: #9f1239;
                color: var(--rse-a-text);
                font-variant-numeric: tabular-nums;
            }

            .dark .rse-dfa-root {
                --rse-a-surface: var(--rse-slate-surface, #0f172a);
                --rse-a-muted: var(--rse-slate-muted, #1e293b);
                --rse-a-border: var(--rse-slate-border-strong, #64748b);
                --rse-a-divider: var(--rse-slate-border, #334155);
                --rse-a-text: var(--rse-slate-text, #f8fafc);
                --rse-a-text-secondary: var(--rse-slate-text-secondary, #cbd5e1);
                --rse-a-accent: var(--rse-brand-accent, #1997e6);
                --rse-a-accent-text: var(--rse-brand-accent-text, #0b0f19);
                --rse-a-link: var(--rse-brand-link, #38bdf8);
                --rse-a-track: var(--rse-slate-track, #1e293b);
                --rse-a-badge-bg: var(--rse-brand-badge-bg, #052d47);
                --rse-a-badge-text: var(--rse-brand-badge-text, #bae0f4);
                --rse-a-err-bg: #4c0519;
                --rse-a-err-text: #fecdd3;
            }

            .rse-dfa-panel {
                padding: 1.25rem;
                border-radius: 0.75rem;
                background-color: var(--rse-a-surface);
                border: 1px solid var(--rse-a-divider);
                margin-bottom: 1.25rem;
            }

            .rse-dfa-filter-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 0.875rem;
                align-items: end;
            }

            @media (min-width: 640px) {
                .rse-dfa-filter-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (min-width: 1024px) {
                .rse-dfa-filter-grid {
                    grid-template-columns: 1.5fr 1fr 1fr auto;
                }
            }

            .rse-dfa-input {
                width: 100%;
                min-height: 44px;
                padding: 0.5rem 0.75rem;
                border-radius: 0.5rem;
                border: 1px solid var(--rse-a-border);
                background-color: var(--rse-a-surface);
                color: var(--rse-a-text);
                font-size: 0.875rem;
            }

            .rse-dfa-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-height: 44px;
                padding: 0.5rem 1rem;
                border-radius: 0.5rem;
                border: 1px solid var(--rse-a-border);
                background-color: var(--rse-a-muted);
                color: var(--rse-a-text);
                font-size: 0.875rem;
                font-weight: 600;
                cursor: pointer;
                text-decoration: none;
            }

            .rse-dfa-input:focus-visible,
            .rse-dfa-btn:focus-visible,
            .rse-dfa-link:focus-visible {
                outline: 2px solid var(--rse-a-accent);
                outline-offset: 2px;
            }

            .rse-dfa-kpi-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 0.875rem;
                margin-bottom: 1.5rem;
            }

            @media (min-width: 640px) {
                .rse-dfa-kpi-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (min-width: 1024px) {
                .rse-dfa-kpi-grid {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }
            }

            .rse-dfa-kpi {
                padding: 1.125rem;
                border-radius: 0.75rem;
                background-color: var(--rse-a-surface);
                border: 1px solid var(--rse-a-divider);
            }

            .rse-dfa-bar-track {
                width: 100%;
                height: 0.75rem;
                border-radius: 9999px;
                background-color: var(--rse-a-track);
                overflow: hidden;
            }

            .rse-dfa-bar-fill {
                height: 100%;
                border-radius: 9999px;
                background-color: var(--rse-a-accent);
            }

            .rse-dfa-table-wrap {
                overflow-x: auto;
                border: 1px solid var(--rse-a-divider);
                border-radius: 0.5rem;
            }

            .rse-dfa-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 0.875rem;
            }

            .rse-dfa-table th,
            .rse-dfa-table td {
                padding: 0.75rem 0.875rem;
                border-bottom: 1px solid var(--rse-a-divider);
                text-align: left;
                vertical-align: middle;
            }

            .rse-dfa-table th {
                background-color: var(--rse-a-muted);
                font-size: 0.75rem;
                font-weight: 700;
                color: var(--rse-a-text-secondary);
            }

            .rse-dfa-link {
                color: var(--rse-a-link);
                font-weight: 600;
                text-decoration: underline;
                text-underline-offset: 2px;
            }
        </style>

        <div class="rse-dfa-panel">
            <div class="rse-dfa-filter-grid">
                <div>
                    <label for="dfa-version" style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.375rem; color: var(--rse-a-text-secondary);">
                        {{ __('Versi Formulir') }}
                    </label>
                    <select id="dfa-version" class="rse-dfa-input" wire:model.live="versionFilter">
                        <option value="all">{{ __('Semua Versi (Penyelarasan field_key)') }}</option>
                        @foreach ($versions as $ver)
                            <option value="{{ $ver->id }}">
                                {{ __('Versi v:version (:status)', ['version' => $ver->version_number, 'status' => strtoupper($ver->status)]) }}
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="dfa-from" style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.375rem; color: var(--rse-a-text-secondary);">
                        {{ __('Dari Tanggal') }}
                    </label>
                    <input id="dfa-from" type="date" class="rse-dfa-input" wire:model.live="dateFrom" />
                </div>

                <div>
                    <label for="dfa-to" style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.375rem; color: var(--rse-a-text-secondary);">
                        {{ __('Sampai Tanggal') }}
                    </label>
                    <input id="dfa-to" type="date" class="rse-dfa-input" wire:model.live="dateTo" />
                </div>

                <div>
                    <button type="button" wire:click="resetFilters" class="rse-dfa-btn" style="width: 100%;">
                        {{ __('Reset Filter') }}
                    </button>
                </div>
            </div>

            <div wire:loading.flex wire:target="versionFilter,dateFrom,dateTo,resetFilters" style="align-items: center; gap: 0.5rem; margin-top: 0.875rem; padding: 0.5rem 0.75rem; border-radius: 0.5rem; background-color: var(--rse-a-muted); font-size: 0.8125rem;">
                <span>{{ __('Menyusun ulang statistik analitik berdasarkan filter aktif...') }}</span>
            </div>
        </div>

        @if ($errorMessage)
            <div role="alert" style="padding: 1rem 1.25rem; border-radius: 0.75rem; background-color: var(--rse-a-err-bg); color: var(--rse-a-err-text); margin-bottom: 1.25rem;">
                <p style="font-weight: 700; margin: 0 0 0.25rem;">{{ __('Terjadi Kesalahan Analitik') }}</p>
                <p style="font-size: 0.875rem; margin: 0 0 0.75rem;">{{ $errorMessage }}</p>
                <button type="button" wire:click="resetFilters" class="rse-dfa-btn">{{ __('Muat Ulang Filter Bawaan') }}</button>
            </div>
        @endif

        <div class="rse-dfa-kpi-grid">
            <div class="rse-dfa-kpi">
                <p style="font-size: 0.75rem; font-weight: 700; color: var(--rse-a-text-secondary); margin: 0 0 0.375rem;">
                    {{ __('Total Respons Terfilter') }}
                </p>
                <p style="font-size: 1.75rem; font-weight: 800; margin: 0;">{{ $totalResponses }}</p>
            </div>

            <div class="rse-dfa-kpi">
                <p style="font-size: 0.75rem; font-weight: 700; color: var(--rse-a-text-secondary); margin: 0 0 0.375rem;">
                    {{ __('Rata-Rata Keterisian Pertanyaan') }}
                </p>
                <p style="font-size: 1.75rem; font-weight: 800; margin: 0;">
                    {{ $avgFillRate !== null ? $avgFillRate.'%' : __('Belum ada data') }}
                </p>
            </div>

            <div class="rse-dfa-kpi">
                <p style="font-size: 0.75rem; font-weight: 700; color: var(--rse-a-text-secondary); margin: 0 0 0.375rem;">
                    {{ __('Respons Terakhir Masuk') }}
                </p>
                <p style="font-size: 1rem; font-weight: 700; margin: 0;">
                    {{ $lastSubmittedAt ? $lastSubmittedAt->format('d M Y, H:i') : __('Belum ada respons') }}
                </p>
            </div>
        </div>

        @if ($totalResponses === 0)
            <div class="rse-dfa-panel" style="text-align: center; padding: 2.5rem 1.5rem;">
                @if (! empty($hasActiveFilters))
                    <p style="font-size: 1rem; font-weight: 700; margin: 0 0 0.375rem;">
                        {{ __('Tidak ada respons yang cocok dengan filter aktif.') }}
                    </p>
                    <p style="font-size: 0.875rem; color: var(--rse-a-text-secondary); margin: 0 0 1rem;">
                        {{ __('Ubah pilihan versi atau rentang tanggal pengisian untuk menampilkan data respons.') }}
                    </p>
                    <button type="button" wire:click="resetFilters" class="rse-dfa-btn">
                        {{ __('Reset Filter ke Semua Data') }}
                    </button>
                @else
                    <p style="font-size: 1rem; font-weight: 700; margin: 0 0 0.375rem;">
                        {{ __('Belum ada respons masuk untuk formulir ini.') }}
                    </p>
                    <p style="font-size: 0.875rem; color: var(--rse-a-text-secondary); margin: 0 0 1rem;">
                        {{ __('Publikasikan form agar Web Frontend dapat mulai menerima pengisian.') }}
                    </p>
                    <a href="{{ \App\Filament\Resources\DynamicForms\DynamicFormResource::getUrl('edit', ['record' => $formRecord]) }}" class="rse-dfa-btn">
                        {{ __('Periksa Pengaturan & Publikasi Form') }}
                    </a>
                @endif
            </div>
        @else
            @foreach ($questions as $question)
                <div class="rse-dfa-panel">
                    <div style="display: flex; flex-wrap: wrap; align-items: baseline; justify-content: space-between; gap: 0.5rem; margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--rse-a-divider);">
                        <div>
                            <p style="font-size: 0.75rem; font-weight: 600; color: var(--rse-a-text-secondary); margin: 0 0 0.125rem;">
                                {{ $question['section_title'] }} · {{ $question['type_label'] }} ({{ $question['field_key'] }})
                                @if (! empty($question['is_legacy']))
                                    · {{ __('Arsip Versi Lama') }}
                                @endif
                            </p>
                            <h3 style="font-size: 1rem; font-weight: 700; margin: 0;">{{ $question['label'] }}</h3>
                        </div>
                        <span style="padding: 0.25rem 0.625rem; border-radius: 0.375rem; background-color: var(--rse-a-badge-bg); color: var(--rse-a-badge-text); font-size: 0.75rem; font-weight: 700;">
                            {{ __(':count jawaban', ['count' => $question['answered_count']]) }}
                        </span>
                    </div>

                    @if ($question['chart_kind'] === 'choice_distribution')
                        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
                            @foreach ($question['distribution'] as $item)
                                <div>
                                    <div style="display: flex; justify-content: space-between; gap: 0.5rem; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                        <span style="font-weight: 600;">{{ $item['label'] }}</span>
                                        <span style="color: var(--rse-a-text-secondary);">{{ $item['count'] }} ({{ $item['percent'] }}%)</span>
                                    </div>
                                    <div class="rse-dfa-bar-track" role="progressbar" aria-valuenow="{{ $item['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ $item['label'] }}">
                                        <div class="rse-dfa-bar-fill" style="width: {{ min(100, (float) $item['percent']) }}%;"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                        @if (! empty($question['other_samples']))
                            <div style="margin-top: 0.875rem; padding: 0.75rem; border-radius: 0.5rem; background-color: var(--rse-a-muted); font-size: 0.8125rem;">
                                <p style="font-weight: 700; margin: 0 0 0.25rem;">{{ __('Contoh isian Lainnya terbaru:') }}</p>
                                <ul style="margin: 0; padding-left: 1.25rem;">
                                    @foreach ($question['other_samples'] as $sample)
                                        <li>{{ $sample }}</li>
                                    @endforeach
                                </ul>
                            </div>
                        @endif
                    @elseif ($question['chart_kind'] === 'numeric_scale')
                        <div style="display: flex; flex-wrap: wrap; gap: 1rem; margin-bottom: 1rem;">
                            <div style="padding: 0.75rem 1rem; border-radius: 0.5rem; background-color: var(--rse-a-muted); border: 1px solid var(--rse-a-divider);">
                                <span style="font-size: 0.75rem; color: var(--rse-a-text-secondary);">{{ __('Rata-rata:') }}</span>
                                <strong style="font-size: 1.125rem; margin-left: 0.25rem;">{{ $question['average'] ?? '-' }}</strong>
                            </div>
                            <div style="padding: 0.75rem 1rem; border-radius: 0.5rem; background-color: var(--rse-a-muted); border: 1px solid var(--rse-a-divider);">
                                <span style="font-size: 0.75rem; color: var(--rse-a-text-secondary);">{{ __('Median:') }}</span>
                                <strong style="font-size: 1.125rem; margin-left: 0.25rem;">{{ $question['median'] ?? '-' }}</strong>
                            </div>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 0.625rem;">
                            @foreach ($question['distribution'] as $item)
                                <div>
                                    <div style="display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                        <span style="font-weight: 600;">{{ __('Nilai :value', ['value' => $item['label']]) }}</span>
                                        <span style="color: var(--rse-a-text-secondary);">{{ $item['count'] }} ({{ $item['percent'] }}%)</span>
                                    </div>
                                    <div class="rse-dfa-bar-track" role="progressbar" aria-valuenow="{{ $item['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="{{ __('Nilai :value', ['value' => $item['label']]) }}">
                                        <div class="rse-dfa-bar-fill" style="width: {{ min(100, (float) $item['percent']) }}%;"></div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @elseif ($question['chart_kind'] === 'matrix')
                        <div class="rse-dfa-table-wrap">
                            <table class="rse-dfa-table">
                                <thead>
                                    <tr>
                                        <th>{{ __('Butir Pertanyaan') }}</th>
                                        @foreach ($question['columns'] as $col)
                                            <th>{{ $col['label'] }}</th>
                                        @endforeach
                                        <th>{{ __('Rata-rata Skor') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($question['rows'] as $mRow)
                                        <tr>
                                            <td style="font-weight: 600;">{{ $mRow['row_label'] }}</td>
                                            @foreach ($mRow['cells'] as $cell)
                                                <td>{{ $cell['count'] }} ({{ $cell['percent'] }}%)</td>
                                            @endforeach
                                            <td>{{ $mRow['avg_score'] ?? '-' }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @elseif ($question['chart_kind'] === 'files')
                        @if (empty($question['recent_files']))
                            <p style="font-size: 0.875rem; color: var(--rse-a-text-secondary); margin: 0;">{{ __('Belum ada berkas diunggah.') }}</p>
                        @else
                            <div class="rse-dfa-table-wrap">
                                <table class="rse-dfa-table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Nama Berkas') }}</th>
                                            <th>{{ __('Ukuran') }}</th>
                                            <th>{{ __('Waktu Kirim') }}</th>
                                            <th>{{ __('Tindakan') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($question['recent_files'] as $fileItem)
                                            <tr>
                                                <td>{{ $fileItem['original_name'] }}</td>
                                                <td>{{ number_format(((int) $fileItem['size_bytes']) / 1024, 1) }} KB</td>
                                                <td>{{ $fileItem['submitted_at']?->format('d M Y H:i') ?? '-' }}</td>
                                                <td>
                                                    @if ($canViewSubmissions && ! empty($fileItem['upload_id']))
                                                        <div style="display: inline-flex; flex-wrap: wrap; align-items: center; gap: 0.75rem;">
                                                            @if (! empty($fileItem['can_inline_preview']))
                                                                <a class="rse-dfa-link" href="{{ route('filament.admin.dynamic-forms.uploads.download', ['upload' => $fileItem['upload_id'], 'inline' => 1]) }}" target="_blank" rel="noopener noreferrer">
                                                                    {{ __('Lihat') }}
                                                                </a>
                                                            @endif
                                                            <a class="rse-dfa-link" href="{{ route('filament.admin.dynamic-forms.uploads.download', ['upload' => $fileItem['upload_id']]) }}">
                                                                {{ __('Unduh Berkas') }}
                                                            </a>
                                                        </div>
                                                    @else
                                                        <span>{{ __('Akses Terbatas') }}</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @else
                        @if (empty($question['recent_responses']))
                            <p style="font-size: 0.875rem; color: var(--rse-a-text-secondary); margin: 0;">{{ __('Belum ada jawaban teks pada filter ini.') }}</p>
                        @else
                            <div class="rse-dfa-table-wrap">
                                <table class="rse-dfa-table">
                                    <thead>
                                        <tr>
                                            <th>{{ __('Jawaban Responden') }}</th>
                                            <th>{{ __('Waktu Kirim') }}</th>
                                            <th>{{ __('Detail') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($question['recent_responses'] as $resp)
                                            <tr>
                                                <td>{{ \Illuminate\Support\Str::limit($resp['value'], 180) }}</td>
                                                <td style="white-space: nowrap;">{{ $resp['submitted_at']?->format('d M Y H:i') ?? '-' }}</td>
                                                <td style="white-space: nowrap;">
                                                    @if ($canViewSubmissions)
                                                        <a class="rse-dfa-link" href="{{ \App\Filament\Resources\DynamicFormSubmissions\DynamicFormSubmissionResource::getUrl('view', ['record' => $resp['submission_id']]) }}">
                                                            {{ __('Lihat Respons') }}
                                                        </a>
                                                    @else
                                                        <span>-</span>
                                                    @endif
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    @endif
                </div>
            @endforeach
        @endif
    </div>
</x-filament-panels::page>
