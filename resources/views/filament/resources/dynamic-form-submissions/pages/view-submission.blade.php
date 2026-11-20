<x-filament-panels::page>
    @php
        $statusEnum = $submission->status instanceof \App\Enums\FeedbackTicketStatus
            ? $submission->status
            : \App\Enums\FeedbackTicketStatus::tryFrom((string) $submission->status);
        $statusValue = $statusEnum?->value ?? 'baru';
    @endphp

    <div class="rse-dfs-root">
        <style>
            .rse-dfs-root {
                --rse-s-surface: #ffffff;
                --rse-s-muted: #f8fafc;
                --rse-s-border: #64748b;
                --rse-s-divider: #cbd5e1;
                --rse-s-text: #0f172a;
                --rse-s-text-secondary: #475569;
                --rse-s-accent: #b45309;
                --rse-s-amber-bg: #fef3c7;
                --rse-s-amber-text: #92400e;
                --rse-s-sky-bg: #e0f2fe;
                --rse-s-sky-text: #075985;
                --rse-s-ok-bg: #d1fae5;
                --rse-s-ok-text: #065f46;
                color: var(--rse-s-text);
                font-variant-numeric: tabular-nums;
            }

            .dark .rse-dfs-root {
                --rse-s-surface: #18181b;
                --rse-s-muted: #0f172a;
                --rse-s-border: #71717a;
                --rse-s-divider: #3f3f46;
                --rse-s-text: #f8fafc;
                --rse-s-text-secondary: #cbd5e1;
                --rse-s-accent: #fbbf24;
                --rse-s-amber-bg: #451a03;
                --rse-s-amber-text: #fde68a;
                --rse-s-sky-bg: #082f49;
                --rse-s-sky-text: #bae6fd;
                --rse-s-ok-bg: #064e3b;
                --rse-s-ok-text: #a7f3d0;
            }

            .rse-dfs-card {
                padding: 1.25rem;
                border-radius: 0.75rem;
                background-color: var(--rse-s-surface);
                border: 1px solid var(--rse-s-divider);
                margin-bottom: 1.25rem;
            }

            .rse-dfs-meta-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 0.875rem;
            }

            @media (min-width: 640px) {
                .rse-dfs-meta-grid {
                    grid-template-columns: repeat(2, minmax(0, 1fr));
                }
            }

            @media (min-width: 1024px) {
                .rse-dfs-meta-grid {
                    grid-template-columns: repeat(3, minmax(0, 1fr));
                }
            }

            .rse-dfs-item {
                padding: 0.875rem 1rem;
                border-radius: 0.5rem;
                background-color: var(--rse-s-muted);
                border: 1px solid var(--rse-s-divider);
                margin-bottom: 0.75rem;
            }

            .rse-dfs-table-wrap {
                overflow-x: auto;
                border: 1px solid var(--rse-s-divider);
                border-radius: 0.5rem;
                background-color: var(--rse-s-surface);
            }

            .rse-dfs-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 0.875rem;
            }

            .rse-dfs-table th,
            .rse-dfs-table td {
                padding: 0.625rem 0.875rem;
                border-bottom: 1px solid var(--rse-s-divider);
                text-align: left;
            }

            .rse-dfs-download-btn {
                display: inline-flex;
                align-items: center;
                min-height: 44px;
                padding: 0.375rem 0.875rem;
                border-radius: 0.5rem;
                border: 1px solid var(--rse-s-border);
                background-color: var(--rse-s-surface);
                color: var(--rse-s-accent);
                font-size: 0.8125rem;
                font-weight: 700;
                text-decoration: none;
            }

            .rse-dfs-download-btn:focus-visible {
                outline: 2px solid var(--rse-s-accent);
                outline-offset: 2px;
            }
        </style>

        <div class="rse-dfs-card">
            <h2 style="font-size: 1rem; font-weight: 700; margin: 0 0 1rem;">Metadata Pengisian</h2>
            <div class="rse-dfs-meta-grid">
                <div>
                    <p style="font-size: 0.75rem; color: var(--rse-s-text-secondary); margin: 0;">Formulir</p>
                    <p style="font-size: 0.875rem; font-weight: 700; margin: 0.125rem 0 0;">
                        {{ $submission->form?->title ?? '-' }}
                    </p>
                </div>
                <div>
                    <p style="font-size: 0.75rem; color: var(--rse-s-text-secondary); margin: 0;">Mode & Versi</p>
                    <p style="font-size: 0.875rem; font-weight: 700; margin: 0.125rem 0 0;">
                        {{ $submission->form?->mode?->label() ?? '-' }} · v{{ $submission->version?->version_number ?? '?' }}
                    </p>
                </div>
                <div>
                    <p style="font-size: 0.75rem; color: var(--rse-s-text-secondary); margin: 0;">Waktu Dikirim</p>
                    <p style="font-size: 0.875rem; font-weight: 700; margin: 0.125rem 0 0;">
                        {{ optional($submission->submitted_at)->format('d M Y, H:i') ?? '-' }}
                    </p>
                </div>
                @if ($isFeedback)
                    <div>
                        <p style="font-size: 0.75rem; color: var(--rse-s-text-secondary); margin: 0;">Nomor Tiket</p>
                        <p style="font-size: 0.875rem; font-weight: 700; margin: 0.125rem 0 0;">
                            {{ $submission->ticket_number ?? '-' }}
                        </p>
                    </div>
                @endif
                <div>
                    <p style="font-size: 0.75rem; color: var(--rse-s-text-secondary); margin: 0;">Bahasa / Sumber</p>
                    <p style="font-size: 0.875rem; font-weight: 700; margin: 0.125rem 0 0;">
                        {{ strtoupper((string) $submission->locale) }} · {{ $submission->source }}
                    </p>
                </div>
            </div>
        </div>

        @if ($isFeedback)
            @php
                $badgeStyle = match ($statusValue) {
                    'selesai' => 'background-color: var(--rse-s-ok-bg); color: var(--rse-s-ok-text);',
                    'diproses' => 'background-color: var(--rse-s-sky-bg); color: var(--rse-s-sky-text);',
                    default => 'background-color: var(--rse-s-amber-bg); color: var(--rse-s-amber-text);',
                };
            @endphp
            <div class="rse-dfs-card">
                <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem; margin-bottom: 0.75rem;">
                    <h2 style="font-size: 1rem; font-weight: 700; margin: 0;">Status & Catatan Tindak Lanjut</h2>
                    <span style="padding: 0.25rem 0.75rem; border-radius: 0.375rem; font-size: 0.75rem; font-weight: 700; {{ $badgeStyle }}">
                        {{ $statusEnum?->label() ?? ucfirst($statusValue) }}
                    </span>
                </div>
                <p style="font-size: 0.875rem; color: var(--rse-s-text-secondary); margin: 0;">
                    {{ filled($submission->internal_note) ? $submission->internal_note : 'Belum ada catatan tindak lanjut internal.' }}
                </p>
            </div>
        @endif

        @if ($renderedSections === [] && $orphanItems === [])
            <div class="rse-dfs-card" style="text-align: center; padding: 2rem;">
                <p style="font-weight: 700; margin: 0 0 0.25rem;">Tidak ada jawaban tersimpan pada pengisian ini.</p>
                <p style="font-size: 0.875rem; color: var(--rse-s-text-secondary); margin: 0;">
                    Data jawaban responden bersifat permanen dan tidak dapat diubah manual oleh staf.
                </p>
            </div>
        @else
            @foreach ($renderedSections as $sec)
                <div class="rse-dfs-card">
                    <h2 style="font-size: 1rem; font-weight: 700; margin: 0 0 1rem;">{{ $sec['title'] }}</h2>

                    @foreach ($sec['items'] as $item)
                        <div class="rse-dfs-item">
                            <p style="font-size: 0.875rem; font-weight: 700; margin: 0 0 0.375rem;">
                                {{ $item['label'] }}
                            </p>

                            @if (! empty($item['display_list']))
                                <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.875rem;">
                                    @foreach ($item['display_list'] as $optLabel)
                                        <li>{{ $optLabel }}</li>
                                    @endforeach
                                </ul>
                            @elseif (! empty($item['matrix_rows']))
                                <div class="rse-dfs-table-wrap">
                                    <table class="rse-dfs-table">
                                        <thead>
                                            <tr>
                                                <th>Butir</th>
                                                <th>Jawaban</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($item['matrix_rows'] as $mRow)
                                                <tr>
                                                    <td style="font-weight: 600;">{{ $mRow['row_label'] }}</td>
                                                    <td>{{ $mRow['answer_label'] }}</td>
                                                </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            @elseif (! empty($item['files']))
                                <div style="display: flex; flex-direction: column; gap: 0.5rem;">
                                    @foreach ($item['files'] as $fileItem)
                                        <div style="display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: 0.5rem; padding: 0.5rem 0.75rem; border-radius: 0.375rem; background-color: var(--rse-s-surface); border: 1px solid var(--rse-s-divider);">
                                            <div>
                                                <p style="font-size: 0.875rem; font-weight: 600; margin: 0;">{{ $fileItem['original_name'] }}</p>
                                                <p style="font-size: 0.75rem; color: var(--rse-s-text-secondary); margin: 0;">
                                                    {{ $fileItem['mime_type'] }} · {{ number_format(((int) $fileItem['size_bytes']) / 1024, 1) }} KB
                                                </p>
                                            </div>
                                            @if (! empty($fileItem['download_url']))
                                                <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;">
                                                    @if (! empty($fileItem['preview_url']))
                                                        <a class="rse-dfs-download-btn" href="{{ $fileItem['preview_url'] }}" target="_blank" rel="noopener noreferrer">
                                                            Lihat
                                                        </a>
                                                    @endif
                                                    <a class="rse-dfs-download-btn" href="{{ $fileItem['download_url'] }}">
                                                        Unduh Lampiran
                                                    </a>
                                                </div>
                                            @else
                                                <span style="font-size: 0.75rem; color: var(--rse-s-text-secondary); font-weight: 600;">
                                                    {{ $canDownload ? 'Berkas tidak tersedia' : 'Akses unduh terbatas' }}
                                                </span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @else
                                <p style="font-size: 0.875rem; margin: 0; color: {{ ! empty($item['is_answered']) ? 'var(--rse-s-text)' : 'var(--rse-s-text-secondary)' }};">
                                    {{ $item['display_text'] ?? '-' }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endforeach

            @if ($orphanItems !== [])
                <div class="rse-dfs-card">
                    <h2 style="font-size: 1rem; font-weight: 700; margin: 0 0 1rem;">Jawaban Tambahan (Kunci Arsip)</h2>
                    @foreach ($orphanItems as $item)
                        <div class="rse-dfs-item">
                            <p style="font-size: 0.875rem; font-weight: 700; margin: 0 0 0.375rem;">
                                {{ $item['label'] }}
                            </p>
                            <p style="font-size: 0.875rem; margin: 0;">
                                {{ $item['display_text'] ?? '-' }}
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</x-filament-panels::page>
