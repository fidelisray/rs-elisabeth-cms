<x-filament-panels::page>
    @php
        $webhookStatus = $webhookLast['status'] ?? null;
        $doctorSyncStatus = $doctorSyncLast['status'] ?? null;
        $failedTotal = (int) ($failedJobs['total'] ?? 0);
        $failedRows = $failedJobs['recent'] ?? [];
        $activeDoctors = (int) ($flags['active_doctors_count'] ?? 0);
        $completeDoctors = (int) ($flags['complete_doctors_count'] ?? 0);
        $doctorCompletionPct = $activeDoctors > 0 ? (int) round(($completeDoctors / $activeDoctors) * 100) : 0;
        $blacklist = $flags['rs_api_blacklist_specialties'] ?? [];

        $statusLabel = static function (?string $status): string {
            return match ($status) {
                'success' => __('Berhasil'),
                'partial' => __('Parsial'),
                'failed' => __('Gagal'),
                'disabled' => __('Nonaktif'),
                'unconfigured' => __('Belum Dikonfigurasi'),
                default => __('Belum Ada Data'),
            };
        };

        $statusTone = static function (?string $status): string {
            return match ($status) {
                'success' => 'success',
                'partial', 'unconfigured' => 'warning',
                'failed' => 'danger',
                default => 'neutral',
            };
        };
    @endphp

    <style>
        .rse-sm-root {
            --rse-surface: var(--rse-slate-surface, #ffffff);
            --rse-surface-muted: var(--rse-slate-muted, #f8fafc);
            --rse-border: var(--rse-slate-border, #cbd5e1);
            --rse-border-subtle: var(--rse-slate-divider, #e2e8f0);
            --rse-text: var(--rse-slate-text, #0f172a);
            --rse-text-secondary: var(--rse-slate-text-secondary, #475569);
            color: var(--rse-text);
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            font-variant-numeric: tabular-nums;
        }

        .dark .rse-sm-root {
            --rse-surface: var(--rse-slate-surface, #0f172a);
            --rse-surface-muted: var(--rse-slate-muted, #1e293b);
            --rse-border: var(--rse-slate-border, #334155);
            --rse-border-subtle: var(--rse-slate-divider, #233044);
            --rse-text: var(--rse-slate-text, #f8fafc);
            --rse-text-secondary: var(--rse-slate-text-secondary, #cbd5e1);
        }

        .rse-sm-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        @media (min-width: 1024px) {
            .rse-sm-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        .rse-sm-card {
            background-color: var(--rse-surface);
            border: 1px solid var(--rse-border);
            border-radius: 0.75rem;
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.875rem;
        }

        .rse-sm-card-head {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
        }

        .rse-sm-card-title {
            font-size: 0.9375rem;
            font-weight: 700;
            color: var(--rse-text);
            margin: 0;
        }

        .rse-sm-card-sub {
            font-size: 0.8125rem;
            color: var(--rse-text-secondary);
            margin: 0.125rem 0 0;
        }

        .rse-sm-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .rse-sm-badge--success {
            background-color: #d1fae5;
            color: #065f46;
        }

        .dark .rse-sm-badge--success {
            background-color: #064e3b;
            color: #a7f3d0;
        }

        .rse-sm-badge--warning {
            background-color: #fef3c7;
            color: #92400e;
        }

        .dark .rse-sm-badge--warning {
            background-color: #451a03;
            color: #fde68a;
        }

        .rse-sm-badge--danger {
            background-color: #ffe4e6;
            color: #9f1239;
        }

        .dark .rse-sm-badge--danger {
            background-color: #4c0519;
            color: #fecdd3;
        }

        .rse-sm-badge--neutral {
            background-color: var(--rse-surface-muted);
            color: var(--rse-text-secondary);
            border: 1px solid var(--rse-border);
        }

        .rse-sm-dl {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.5rem;
            margin: 0;
            padding-top: 0.75rem;
            border-top: 1px solid var(--rse-border-subtle);
        }

        .rse-sm-row {
            display: flex;
            justify-content: space-between;
            align-items: baseline;
            gap: 0.75rem;
            font-size: 0.8125rem;
        }

        .rse-sm-row dt {
            color: var(--rse-text-secondary);
            font-weight: 500;
        }

        .rse-sm-row dd {
            margin: 0;
            color: var(--rse-text);
            font-weight: 600;
            text-align: right;
            word-break: break-word;
        }

        .rse-sm-note {
            background-color: var(--rse-surface-muted);
            border: 1px solid var(--rse-border-subtle);
            border-radius: 0.5rem;
            padding: 0.625rem 0.75rem;
            font-size: 0.78125rem;
            color: var(--rse-text-secondary);
            line-height: 1.5;
        }

        .rse-sm-code {
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.75rem;
            background-color: var(--rse-surface);
            border: 1px solid var(--rse-border);
            border-radius: 0.25rem;
            padding: 0.0625rem 0.375rem;
            color: var(--rse-text);
        }

        .rse-sm-table-wrap {
            width: 100%;
            overflow-x: auto;
        }

        .rse-sm-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }

        .rse-sm-table th {
            text-align: left;
            padding: 0.625rem 0.75rem;
            border-bottom: 1px solid var(--rse-border);
            color: var(--rse-text-secondary);
            font-weight: 600;
            background-color: var(--rse-surface-muted);
            white-space: nowrap;
        }

        .rse-sm-table td {
            padding: 0.625rem 0.75rem;
            border-bottom: 1px solid var(--rse-border-subtle);
            color: var(--rse-text);
            vertical-align: top;
        }

        .rse-sm-empty {
            padding: 1.25rem;
            border-radius: 0.5rem;
            background-color: var(--rse-surface-muted);
            border: 1px dashed var(--rse-border);
            text-align: center;
            font-size: 0.875rem;
            color: var(--rse-text-secondary);
        }
    </style>

    <div class="rse-sm-root" data-rse-system-monitor>
        <div class="rse-sm-grid">
            {{-- 1. Web Frontend Webhook --}}
            <section class="rse-sm-card" aria-labelledby="rse-sm-webhook-heading">
                <div class="rse-sm-card-head">
                    <div>
                        <h2 id="rse-sm-webhook-heading" class="rse-sm-card-title">{{ __('Webhook Website Publik') }}</h2>
                        <p class="rse-sm-card-sub">{{ __('Invalidasi cache instan ke rs-elisabeth-web') }}</p>
                    </div>
                    <span class="rse-sm-badge rse-sm-badge--{{ $flags['webhook_configured'] ? 'success' : 'warning' }}">
                        {{ $flags['webhook_configured'] ? __('Terkonfigurasi') : __('Belum Dikonfigurasi') }}
                    </span>
                </div>

                <dl class="rse-sm-dl">
                    <div class="rse-sm-row">
                        <dt>{{ __('Status Pengiriman Terakhir') }}</dt>
                        <dd>
                            <span class="rse-sm-badge rse-sm-badge--{{ $statusTone($webhookStatus) }}">
                                {{ $statusLabel($webhookStatus) }}
                            </span>
                        </dd>
                    </div>
                    <div class="rse-sm-row">
                        <dt>{{ __('Modul Terakhir') }}</dt>
                        <dd>{{ $webhookLast ? ($moduleLabels[$webhookLast['module'] ?? ''] ?? ($webhookLast['module'] ?? '-')) : '-' }}</dd>
                    </div>
                    <div class="rse-sm-row">
                        <dt>{{ __('Kode HTTP / Timeout') }}</dt>
                        <dd>
                            {{ isset($webhookLast['http_status']) && $webhookLast['http_status'] !== null ? 'HTTP '.$webhookLast['http_status'] : '-' }}
                            · {{ $flags['webhook_timeout'] }}s
                        </dd>
                    </div>
                    <div class="rse-sm-row">
                        <dt>{{ __('Waktu Tercatat') }}</dt>
                        <dd>{{ $webhookLastAt ?? __('Belum ada riwayat pada sesi cache ini') }}</dd>
                    </div>
                </dl>

                @if (! empty($webhookLast['error_summary']))
                    <div class="rse-sm-note">
                        <strong>{{ __('Ringkasan Error Terakhir:') }}</strong>
                        {{ $webhookLast['error_summary'] }}
                    </div>
                @else
                    <div class="rse-sm-note">
                        {{ __('URL rahasia, Consumer ID, dan Secret Key tidak pernah ditampilkan di antarmuka web.') }}
                    </div>
                @endif
            </section>

            {{-- 2. SIMRS MedInfras Sync --}}
            <section class="rse-sm-card" aria-labelledby="rse-sm-simrs-heading">
                <div class="rse-sm-card-head">
                    <div>
                        <h2 id="rse-sm-simrs-heading" class="rse-sm-card-title">{{ __('Sinkronisasi SIMRS MedInfras') }}</h2>
                        <p class="rse-sm-card-sub">{{ __('Jadwal otomatis harian 04:00 WIB (CLI / Scheduler)') }}</p>
                    </div>
                    <span class="rse-sm-badge rse-sm-badge--{{ $flags['rs_api_enabled'] ? 'success' : 'neutral' }}">
                        {{ $flags['rs_api_enabled'] ? __('Aktif') : __('Nonaktif') }}
                    </span>
                </div>

                <dl class="rse-sm-dl">
                    <div class="rse-sm-row">
                        <dt>{{ __('Status Sinkronisasi Terakhir') }}</dt>
                        <dd>
                            <span class="rse-sm-badge rse-sm-badge--{{ $statusTone($doctorSyncStatus) }}">
                                {{ $statusLabel($doctorSyncStatus) }}
                            </span>
                        </dd>
                    </div>
                    <div class="rse-sm-row">
                        <dt>{{ __('Dokter Aktif / Profil Lengkap') }}</dt>
                        <dd>{{ $activeDoctors }} / {{ $completeDoctors }} ({{ $doctorCompletionPct }}%)</dd>
                    </div>
                    <div class="rse-sm-row">
                        <dt>{{ __('Hasil Sync Terakhir') }}</dt>
                        <dd>
                            @if ($doctorSyncLast)
                                {{ __('+:created baru, :updated diubah, :unchanged tetap', [
                                    'created' => (int) ($doctorSyncLast['created'] ?? 0),
                                    'updated' => (int) ($doctorSyncLast['updated'] ?? 0),
                                    'unchanged' => (int) ($doctorSyncLast['unchanged'] ?? 0),
                                ]) }}
                            @else
                                -
                            @endif
                        </dd>
                    </div>
                    <div class="rse-sm-row">
                        <dt>{{ __('Spesialisasi Dikecualikan') }}</dt>
                        <dd>{{ $blacklist !== [] ? implode(', ', $blacklist) : __('Tidak ada') }}</dd>
                    </div>
                    <div class="rse-sm-row">
                        <dt>{{ __('Waktu Tercatat') }}</dt>
                        <dd>{{ $doctorSyncLastAt ?? __('Belum ada riwayat pada sesi cache ini') }}</dd>
                    </div>
                </dl>

                <div class="rse-sm-note">
                    {{ __('Sinkronisasi manual SIMRS dijalankan melalui konsol server:') }}
                    <span class="rse-sm-code">php artisan dokter:refresh</span>
                </div>
            </section>

            {{-- 3. Runtime & Storage Config --}}
            <section class="rse-sm-card" aria-labelledby="rse-sm-runtime-heading">
                <div class="rse-sm-card-head">
                    <div>
                        <h2 id="rse-sm-runtime-heading" class="rse-sm-card-title">{{ __('Runtime & Penyimpanan') }}</h2>
                        <p class="rse-sm-card-sub">{{ __('Konfigurasi operasional aktif non-sensitif') }}</p>
                    </div>
                    <span class="rse-sm-badge rse-sm-badge--{{ $failedTotal > 0 ? 'danger' : 'success' }}">
                        {{ $failedTotal > 0 ? __(':count Antrean Gagal', ['count' => $failedTotal]) : __('Antrean Sehat') }}
                    </span>
                </div>

                <dl class="rse-sm-dl">
                    <div class="rse-sm-row">
                        <dt>{{ __('Klien API CMS Terkonfigurasi') }}</dt>
                        <dd>{{ __(':count klien', ['count' => (int) $flags['cms_api_clients_count']]) }}</dd>
                    </div>
                    <div class="rse-sm-row">
                        <dt>{{ __('URL Publik Web (QR & Share)') }}</dt>
                        <dd>{{ $flags['public_url_configured'] ? __('Terkonfigurasi') : __('Belum Dikonfigurasi') }}</dd>
                    </div>
                    <div class="rse-sm-row">
                        <dt>{{ __('Disk Media Publik / Privat') }}</dt>
                        <dd>{{ $flags['media_disk'] }} / {{ $flags['private_disk'] }}</dd>
                    </div>
                    <div class="rse-sm-row">
                        <dt>{{ __('Cache Store / Queue Driver') }}</dt>
                        <dd>{{ $flags['cache_store'] }} / {{ $flags['queue_connection'] }}</dd>
                    </div>
                    <div class="rse-sm-row">
                        <dt>{{ __('Zona Waktu Aplikasi') }}</dt>
                        <dd>{{ $flags['app_timezone'] }}</dd>
                    </div>
                </dl>

                <div class="rse-sm-note">
                    {{ __('Pembersihan unggahan sementara formulir berjalan otomatis setiap 02:30 WIB.') }}
                </div>
            </section>
        </div>

        {{-- 4. Failed Jobs Safe Read-Only Table --}}
        <section class="rse-sm-card" aria-labelledby="rse-sm-failed-jobs-heading">
            <div class="rse-sm-card-head">
                <div>
                    <h2 id="rse-sm-failed-jobs-heading" class="rse-sm-card-title">{{ __('Antrean Gagal (Read-Only Aman)') }}</h2>
                    <p class="rse-sm-card-sub">
                        {{ __('Menampilkan hingga 10 kegagalan terbaru tanpa payload mentah untuk mencegah kebocoran PII atau kredensial.') }}
                    </p>
                </div>
                <span class="rse-sm-badge rse-sm-badge--{{ $failedTotal > 0 ? 'danger' : 'success' }}">
                    {{ __('Total: :count', ['count' => $failedTotal]) }}
                </span>
            </div>

            @if ($failedRows === [])
                <div class="rse-sm-empty">
                    {{ __('Tidak ada antrean gagal saat ini. Seluruh background job berjalan normal.') }}
                </div>
            @else
                <div class="rse-sm-table-wrap">
                    <table class="rse-sm-table">
                        <thead>
                            <tr>
                                <th scope="col">{{ __('UUID') }}</th>
                                <th scope="col">{{ __('Nama Job') }}</th>
                                <th scope="col">{{ __('Antrean') }}</th>
                                <th scope="col">{{ __('Waktu Gagal') }}</th>
                                <th scope="col">{{ __('Ringkasan Error Tersanitasi') }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($failedRows as $job)
                                <tr>
                                    <td><span class="rse-sm-code">{{ \Illuminate\Support\Str::limit($job['uuid'], 16, '...') }}</span></td>
                                    <td><strong>{{ $job['job'] }}</strong></td>
                                    <td>{{ $job['queue'] }}</td>
                                    <td style="white-space: nowrap;">{{ $job['failed_at'] }}</td>
                                    <td>{{ $job['error_summary'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="rse-sm-note">
                {{ __('Untuk mengulang atau membersihkan antrean gagal, jalankan perintah CLI melalui konsol server:') }}
                <span class="rse-sm-code">php artisan queue:failed</span>,
                <span class="rse-sm-code">php artisan queue:retry &lt;uuid&gt;</span>
            </div>
        </section>
    </div>
</x-filament-panels::page>
