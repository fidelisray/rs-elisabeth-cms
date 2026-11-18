<x-filament-panels::page>
    @php
        $summary = $analytics['summary'] ?? [];
        $questions = $analytics['questions'] ?? [];
        $totalResponses = (int) ($summary['total_submissions'] ?? 0);
        $lastSubmittedAt = $summary['last_submitted_at'] ?? null;
        $avgFillRate = $summary['avg_fill_rate'] ?? null;
        $statusCounts = (array) ($summary['status_counts'] ?? []);
        $isFeedbackMode = $formRecord->mode === \App\Enums\DynamicFormMode::Feedback;
    @endphp

    <div class="rse-dfa-root">
        <style>
            .rse-dfa-root {
                --rse-a-surface: #ffffff;
                --rse-a-muted: #f8fafc;
                --rse-a-border: #64748b;
                --rse-a-divider: #cbd5e1;
                --rse-a-text: #0f172a;
                --rse-a-text-secondary: #475569;
                --rse-a-accent: #b45309;
                --rse-a-accent-text: #ffffff;
                --rse-a-track: #e2e8f0;
                --rse-a-badge-bg: #fef3c7;
                --rse-a-badge-text: #92400e;
                --rse-a-err-bg: #ffe4e6;
                --rse-a-err-text: #9f1239;
                color: var(--rse-a-text);
                font-variant-numeric: tabular-nums;
            }

            .dark .rse-dfa-root {
                --rse-a-surface: #18181b;
                --rse-a-muted: #0f172a;
                --rse-a-border: #71717a;
                --rse-a-divider: #3f3f46;
                --rse-a-text: #f8fafc;
                --rse-a-text-secondary: #cbd5e1;
                --rse-a-accent: #fbbf24;
                --rse-a-accent-text: #0f172a;
                --rse-a-track: #27272a;
                --rse-a-badge-bg: #451a03;
                --rse-a-badge-text: #fde68a;
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
                color: var(--rse-a-accent);
                font-weight: 600;
                text-decoration: underline;
                text-underline-offset: 2px;
            }
        </style>

        <div class="rse-dfa-panel">
            <div class="rse-dfa-filter-grid">
                <div>
                    <label for="dfa-version" style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.375rem; color: var(--rse-a-text-secondary);">
                        Versi Formulir
                    </label>
                    <select id="dfa-version" class="rse-dfa-input" wire:model.live="versionFilter">
                        <option value="all">Semua Versi (Penyelarasan field_key)</option>
                        @foreach ($versions as $ver)
                            <option value="{{ $ver->id }}">
                                Versi v{{ $ver->version_number }} ({{ strtoupper($ver->status) }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label for="dfa-from" style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.375rem; color: var(--rse-a-text-secondary);">
                        Dari Tanggal
                    </label>
                    <input id="dfa-from" type="date" class="rse-dfa-input" wire:model.live="dateFrom" />
                </div>

                <div>
                    <label for="dfa-to" style="display: block; font-size: 0.75rem; font-weight: 700; margin-bottom: 0.375rem; color: var(--rse-a-text-secondary);">
                        Sampai Tanggal
                    </label>
                    <input id="dfa-to" type="date" class="rse-dfa-input" wire:model.live="dateTo" />
                </div>

                <div>
                    <button type="button" wire:click="resetFilters" class="rse-dfa-btn" style="width: 100%;">
                        Reset Filter
                    </button>
                </div>
            </div>

            <div wire:loading.flex wire:target="versionFilter,dateFrom,dateTo,resetFilters" style="align-items: center; gap: 0.5rem; margin-top: 0.875rem; padding: 0.5rem 0.75rem; border-radius: 0.5rem; background-color: var(--rse-a-muted); font-size: 0.8125rem;">
                <span>Menyusun ulang statistik analitik berdasarkan filter aktif...</span>
            </div>
        </div>

        @if ($errorMessage)
            <div role="alert" style="padding: 1rem 1.25rem; border-radius: 0.75rem; background-color: var(--rse-a-err-bg); color: var(--rse-a-err-text); margin-bottom: 1.25rem;">
                <p style="font-weight: 700; margin: 0 0 0.25rem;">Terjadi Kesalahan Analitik</p>
                <p style="font-size: 0.875rem; margin: 0 0 0.75rem;">{{ $errorMessage }}</p>
                <button type="button" wire:click="resetFilters" class="rse-dfa-btn">Muat Ulang Filter Bawaan</button>
            </div>
        @endif

        <div class="rse-dfa-kpi-grid">
            <div class="rse-dfa-kpi">
                <p style="font-size: 0.75rem; font-weight: 700; color: var(--rse-a-text-secondary); margin: 0 0 0.375rem;">
                    Total Respons Terfilter
                </p>
                <p style="font-size: 1.75rem; font-weight: 800; margin: 0;">{{ $totalResponses }}</p>
            </div>

            <div class="rse-dfa-kpi">
                @if ($isFeedbackMode)
                    <p style="font-size: 0.75rem; font-weight: 700; color: var(--rse-a-text-secondary); margin: 0 0 0.375rem;">
                        Status Tiket Feedback
                    </p>
                    <p style="font-size: 0.9375rem; font-weight: 700; margin: 0;">
                        Baru: {{ $statusCounts['baru'] ?? 0 }} · Diproses: {{ $statusCounts['diproses'] ?? 0 }} · Selesai: {{ $statusCounts['selesai'] ?? 0 }}
                    </p>
                @else
                    <p style="font-size: 0.75rem; font-weight: 700; color: var(--rse-a-text-secondary); margin: 0 0 0.375rem;">
                        Rata-Rata Keterisian Pertanyaan
                    </p>
                    <p style="font-size: 1.75rem; font-weight: 800; margin: 0;">
                        {{ $avgFillRate !== null ? $avgFillRate.'%' : 'Belum ada data' }}
                    </p>
                @endif
            </div>

            <div class="rse-dfa-kpi">
                <p style="font-size: 0.75rem; font-weight: 700; color: var(--rse-a-text-secondary); margin: 0 0 0.375rem;">
                    Respons Terakhir Masuk
                </p>
                <p style="font-size: 1rem; font-weight: 700; margin: 0;">
                    {{ $lastSubmittedAt ? $lastSubmittedAt->format('d M Y, H:i') : 'Belum ada respons' }}
                </p>
            </div>
        </div>

        @if ($totalResponses === 0)
            <div class="rse-dfa-panel" style="text-align: center; padding: 2.5rem 1.5rem;">
                @if (! empty($hasActiveFilters))
                    <p style="font-size: 1rem; font-weight: 700; margin: 0 0 0.375rem;">
                        Tidak ada respons yang cocok dengan filter aktif.
                    </p>
                    <p style="font-size: 0.875rem; color: var(--rse-a-text-secondary); margin: 0 0 1rem;">
                        Ubah pilihan versi atau rentang tanggal pengisian untuk menampilkan data respons.
                    </p>
                    <button type="button" wire:click="resetFilters" class="rse-dfa-btn">
                        Reset Filter ke Semua Data
                    </button>
                @else
                    <p style="font-size: 1rem; font-weight: 700; margin: 0 0 0.375rem;">
                        Belum ada respons masuk untuk formulir ini.
                    </p>
                    <p style="font-size: 0.875rem; color: var(--rse-a-text-secondary); margin: 0 0 1rem;">
                        Publikasikan form agar Web Frontend dapat mulai menerima pengisian.
                    </p>
                    <a href="{{ \App\Filament\Resources\DynamicForms\DynamicFormResource::getUrl('edit', ['record' => $formRecord]) }}" class="rse-dfa-btn">
                        Periksa Pengaturan & Publikasi Form
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
                                    · Arsip Versi Lama
                                @endif
                            </p>
                            <h3 style="font-size: 1rem; font-weight: 700; margin: 0;">{{ $question['label'] }}</h3>
                        </div>
                        <span style="padding: 0.25rem 0.625rem; border-radius: 0.375rem; background-color: var(--rse-a-badge-bg); color: var(--rse-a-badge-text); font-size: 0.75rem; font-weight: 700;">
                            {{ $question['answered_count'] }} jawaban
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
                                <p style="font-weight: 700; margin: 0 0 0.25rem;">Contoh isian Lainnya terbaru:</p>
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
                                <span style="font-size: 0.75rem; color: var(--rse-a-text-secondary);">Rata-rata:</span>
                                <strong style="font-size: 1.125rem; margin-left: 0.25rem;">{{ $question['average'] ?? '-' }}</strong>
                            </div>
                            <div style="padding: 0.75rem 1rem; border-radius: 0.5rem; background-color: var(--rse-a-muted); border: 1px solid var(--rse-a-divider);">
                                <span style="font-size: 0.75rem; color: var(--rse-a-text-secondary);">Median:</span>
                                <strong style="font-size: 1.125rem; margin-left: 0.25rem;">{{ $question['median'] ?? '-' }}</strong>
                            </div>
                        </div>
                        <div style="display: flex; flex-direction: column; gap: 0.625rem;">
                            @foreach ($question['distribution'] as $item)
                                <div>
                                    <div style="display: flex; justify-content: space-between; font-size: 0.875rem; margin-bottom: 0.25rem;">
                                        <span style="font-weight: 600;">Nilai {{ $item['label'] }}</span>
                                        <span style="color: var(--rse-a-text-secondary);">{{ $item['count'] }} ({{ $item['percent'] }}%)</span>
                                    </div>
                                    <div class="rse-dfa-bar-track" role="progressbar" aria-valuenow="{{ $item['percent'] }}" aria-valuemin="0" aria-valuemax="100" aria-label="Nilai {{ $item['label'] }}">
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
                                        <th>Butir Pertanyaan</th>
                                        @foreach ($question['columns'] as $col)
                                            <th>{{ $col['label'] }}</th>
                                        @endforeach
                                        <th>Rata-rata Skor</th>
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
                            <p style="font-size: 0.875rem; color: var(--rse-a-text-secondary); margin: 0;">Belum ada berkas diunggah.</p>
                        @else
                            <div class="rse-dfa-table-wrap">
                                <table class="rse-dfa-table">
                                    <thead>
                                        <tr>
                                            <th>Nama Berkas</th>
                                            <th>Ukuran</th>
                                            <th>Waktu Kirim</th>
                                            <th>Tindakan</th>
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
                                                        <a class="rse-dfa-link" href="{{ route('filament.admin.dynamic-forms.uploads.download', ['upload' => $fileItem['upload_id']]) }}">
                                                            Unduh Berkas
                                                        </a>
                                                    @else
                                                        <span>Akses Terbatas</span>
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
                            <p style="font-size: 0.875rem; color: var(--rse-a-text-secondary); margin: 0;">Belum ada jawaban teks pada filter ini.</p>
                        @else
                            <div class="rse-dfa-table-wrap">
                                <table class="rse-dfa-table">
                                    <thead>
                                        <tr>
                                            <th>Jawaban Responden</th>
                                            <th>Waktu Kirim</th>
                                            <th>Detail</th>
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
                                                            Lihat Respons
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
