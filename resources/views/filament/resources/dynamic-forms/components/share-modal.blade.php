@php
    $isAccepting = (bool) ($availability['is_accepting_responses'] ?? false);
    $closedReason = $availability['closed_reason'] ?? null;
    $statusNote = match ($closedReason) {
        'closed' => 'Status formulir saat ini Ditutup. Responden yang membuka tautan akan melihat pesan penutupan hingga formulir dibuka kembali.',
        'not_started' => 'Formulir dijadwalkan untuk waktu mendatang. Tautan sudah siap disebarkan, tetapi pengisian baru dibuka sesuai jadwal mulai.',
        'ended' => 'Periode pengisian formulir telah berakhir. Responden yang membuka tautan akan melihat pesan penutupan.',
        'quota_full' => 'Kuota respons formulir sudah penuh. Responden baru tidak dapat mengirim jawaban sebelum kuota ditambah.',
        default => null,
    };
@endphp

<div
    class="rse-share-root"
    x-data="{
        copiedKey: null,
        errorKey: null,
        statusMessage: '',
        resetTimer: null,
        async copyLink(key, inputEl, triggerEl = null) {
            const value = inputEl ? inputEl.value : '';
            if (! value) {
                return;
            }
            let copied = false;
            if (navigator.clipboard && window.isSecureContext) {
                try {
                    await navigator.clipboard.writeText(value);
                    copied = true;
                } catch (err) {
                    copied = false;
                }
            }
            // Jalankan fallback textarea di dalam root modal agar focus-trap Alpine tidak memblokir execCommand dan fokus tidak lompat ke input readonly.
            if (! copied) {
                const tempArea = document.createElement('textarea');
                try {
                    tempArea.value = value;
                    tempArea.setAttribute('readonly', '');
                    tempArea.style.position = 'fixed';
                    tempArea.style.opacity = '0';
                    tempArea.style.pointerEvents = 'none';
                    this.$root.appendChild(tempArea);
                    tempArea.focus({ preventScroll: true });
                    tempArea.select();
                    tempArea.setSelectionRange(0, value.length);
                    copied = document.execCommand('copy');
                } catch (err) {
                    copied = false;
                } finally {
                    if (tempArea.parentNode === this.$root) {
                        this.$root.removeChild(tempArea);
                    }
                }
            }
            if (! copied && inputEl) {
                try {
                    inputEl.focus({ preventScroll: true });
                    inputEl.select();
                    inputEl.setSelectionRange(0, value.length);
                    copied = document.execCommand('copy');
                } catch (err) {
                    copied = false;
                }
            }
            if (triggerEl && typeof triggerEl.focus === 'function') {
                triggerEl.focus({ preventScroll: true });
            }
            clearTimeout(this.resetTimer);
            if (copied) {
                this.copiedKey = key;
                this.errorKey = null;
                this.statusMessage = key === 'qr'
                    ? 'Link QR Code berhasil disalin ke papan klip.'
                    : 'Link Publik Umum berhasil disalin ke papan klip.';
                this.resetTimer = setTimeout(() => {
                    this.copiedKey = null;
                }, 2500);
            } else {
                this.copiedKey = null;
                this.errorKey = key;
                this.statusMessage = 'Penyalinan otomatis diblokir browser. Pilih teks tautan lalu tekan Ctrl+C atau Cmd+C.';
            }
        }
    }"
>
    <style>
        .rse-share-root {
            --rse-s-surface: #ffffff;
            --rse-s-muted: #f8fafc;
            --rse-s-border: #64748b;
            --rse-s-divider: #cbd5e1;
            --rse-s-text: #0f172a;
            --rse-s-text-secondary: #475569;
            --rse-s-accent: #b45309;
            --rse-s-accent-hover: #92400e;
            --rse-s-accent-text: #ffffff;
            --rse-s-pub-bg: #d1fae5;
            --rse-s-pub-text: #065f46;
            --rse-s-unlisted-bg: #fef3c7;
            --rse-s-unlisted-text: #92400e;
            --rse-s-warn-bg: #ffe4e6;
            --rse-s-warn-text: #9f1239;
            color: var(--rse-s-text);
            font-variant-numeric: tabular-nums;
        }

        .dark .rse-share-root {
            --rse-s-surface: #18181b;
            --rse-s-muted: #0f172a;
            --rse-s-border: #71717a;
            --rse-s-divider: #3f3f46;
            --rse-s-text: #f8fafc;
            --rse-s-text-secondary: #cbd5e1;
            --rse-s-accent: #fbbf24;
            --rse-s-accent-hover: #f59e0b;
            --rse-s-accent-text: #0f172a;
            --rse-s-pub-bg: #064e3b;
            --rse-s-pub-text: #a7f3d0;
            --rse-s-unlisted-bg: #451a03;
            --rse-s-unlisted-text: #fde68a;
            --rse-s-warn-bg: #4c0519;
            --rse-s-warn-text: #fecdd3;
        }

        .rse-share-status-card {
            display: flex;
            flex-wrap: wrap;
            align-items: flex-start;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.875rem 1rem;
            border-radius: 0.625rem;
            background-color: var(--rse-s-muted);
            border: 1px solid var(--rse-s-divider);
            margin-bottom: 1rem;
        }

        .rse-share-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.625rem;
            border-radius: 0.375rem;
            font-size: 0.75rem;
            font-weight: 700;
            line-height: 1.25;
        }

        .rse-share-badge-public {
            background-color: var(--rse-s-pub-bg);
            color: var(--rse-s-pub-text);
        }

        .rse-share-badge-unlisted {
            background-color: var(--rse-s-unlisted-bg);
            color: var(--rse-s-unlisted-text);
        }

        .rse-share-alert {
            padding: 0.875rem 1rem;
            border-radius: 0.625rem;
            background-color: var(--rse-s-warn-bg);
            color: var(--rse-s-warn-text);
            font-size: 0.8125rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .rse-share-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr);
            gap: 1rem;
            align-items: start;
        }

        @media (min-width: 640px) {
            .rse-share-grid {
                grid-template-columns: minmax(0, 1.45fr) minmax(0, 1fr);
            }
        }

        .rse-share-stack {
            display: flex;
            flex-direction: column;
            gap: 0.875rem;
            min-width: 0;
        }

        .rse-share-card {
            padding: 1rem;
            border-radius: 0.625rem;
            background-color: var(--rse-s-muted);
            border: 1px solid var(--rse-s-divider);
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .rse-share-input {
            width: 100%;
            min-width: 0;
            min-height: 44px;
            padding: 0.5rem 0.75rem;
            border-radius: 0.5rem;
            border: 1px solid var(--rse-s-border);
            background-color: var(--rse-s-surface);
            color: var(--rse-s-text);
            font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, monospace;
            font-size: 0.8125rem;
            line-height: 1.4;
        }

        .rse-share-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.625rem;
        }

        .rse-share-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.375rem;
            min-height: 44px;
            min-width: 44px;
            padding: 0.5rem 0.875rem;
            border-radius: 0.5rem;
            border: 1px solid var(--rse-s-border);
            background-color: var(--rse-s-surface);
            color: var(--rse-s-text);
            font-size: 0.8125rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 150ms ease, border-color 150ms ease;
        }

        .rse-share-btn:hover {
            border-color: var(--rse-s-accent);
        }

        .rse-share-btn-primary {
            background-color: var(--rse-s-accent);
            border-color: var(--rse-s-accent);
            color: var(--rse-s-accent-text);
        }

        .rse-share-btn-primary:hover {
            background-color: var(--rse-s-accent-hover);
            border-color: var(--rse-s-accent-hover);
        }

        .rse-share-input:focus-visible,
        .rse-share-btn:focus-visible {
            outline: 2px solid var(--rse-s-accent);
            outline-offset: 2px;
        }

        .rse-share-qr-panel {
            padding: 1rem;
            border-radius: 0.625rem;
            background-color: var(--rse-s-muted);
            border: 1px solid var(--rse-s-divider);
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            min-width: 0;
            overflow-wrap: anywhere;
        }

        .rse-share-qr-frame {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 0.75rem;
            border-radius: 0.625rem;
            background-color: #ffffff;
            border: 1px solid #cbd5e1;
            margin: 0.75rem 0;
        }
    </style>

    <div class="rse-share-status-card">
        <div style="min-width: 0; flex: 1 1 14rem;">
            <p style="font-size: 0.75rem; font-weight: 600; color: var(--rse-s-text-secondary); margin: 0 0 0.25rem;">
                Path Web Publik
            </p>
            <p style="font-size: 0.875rem; font-weight: 700; margin: 0; overflow-wrap: anywhere;">
                {{ $relativeWebPath }}
            </p>
            <p style="font-size: 0.75rem; color: var(--rse-s-text-secondary); margin: 0.25rem 0 0;">
                @if ($isPublicListed)
                    Tampil di halaman katalog publik /formulir serta dapat dibuka via tautan langsung atau QR Code.
                @else
                    Tidak ditampilkan di katalog /formulir dan memakai meta noindex. Hanya dapat diakses lewat tautan langsung atau QR Code.
                @endif
            </p>
        </div>
        <div>
            <span class="rse-share-badge {{ $isPublicListed ? 'rse-share-badge-public' : 'rse-share-badge-unlisted' }}">
                {{ $isPublicListed ? 'Katalog Publik' : 'Link / QR Saja' }}
            </span>
        </div>
    </div>

    @if ($statusNote)
        <div class="rse-share-alert" role="status">
            <strong>Catatan Ketersediaan:</strong> {{ $statusNote }}
        </div>
    @endif

    @if (! $isConfigured)
        <div class="rse-share-alert" role="alert">
            <strong>URL Publik Web Belum Dikonfigurasi.</strong>
            Atur variabel <code>WEB_FRONTEND_PUBLIC_URL</code> (atau <code>WEB_FRONTEND_WEBHOOK_URL</code>) di file <code>.env</code> CMS agar tautan lengkap dan QR Code dapat dibuat.
        </div>
    @elseif (! ($hasSlug ?? filled($publicUrl)))
        <div class="rse-share-alert" role="alert">
            <strong>Slug Formulir Belum Tersedia.</strong>
            Lengkapi slug URL Web pada halaman ubah formulir terlebih dahulu agar tautan publik dan QR Code dapat dibuat.
        </div>
    @else
        <div class="sr-only" aria-live="polite" x-text="statusMessage"></div>

        <div class="rse-share-grid">
            <div class="rse-share-stack">
                <div class="rse-share-card">
                    <label for="rse-share-public-url" style="display: block; font-size: 0.875rem; font-weight: 700; margin-bottom: 0.25rem;">
                        Link Publik Umum
                    </label>
                    <p style="font-size: 0.75rem; color: var(--rse-s-text-secondary); margin: 0 0 0.625rem;">
                        Cocok dibagikan ke WhatsApp, situs web, atau pengumuman. Tercatat sebagai sumber <code>web_public</code>.
                    </p>
                    <input
                        id="rse-share-public-url"
                        x-ref="publicUrlInput"
                        type="text"
                        readonly
                        class="rse-share-input"
                        value="{{ $publicUrl }}"
                        aria-label="Link Publik Umum"
                        x-on:click="$el.select()"
                        x-on:focus="$el.select()"
                    />
                    <div class="rse-share-actions">
                        <button
                            type="button"
                            class="rse-share-btn rse-share-btn-primary"
                            x-on:click="copyLink('public', $refs.publicUrlInput, $el)"
                        >
                            <span x-text="copiedKey === 'public' ? 'Tersalin' : 'Salin Link'">Salin Link</span>
                        </button>
                        <a
                            href="{{ $publicUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rse-share-btn"
                        >
                            Buka Tautan
                        </a>
                    </div>
                    <p
                        x-show="errorKey === 'public'"
                        x-cloak
                        role="alert"
                        style="font-size: 0.75rem; color: var(--rse-s-warn-text); margin: 0.5rem 0 0; font-weight: 600;"
                    >
                        Penyalinan otomatis tidak tersedia. Pilih teks pada kolom di atas lalu tekan Ctrl+C atau Cmd+C.
                    </p>
                </div>

                <div class="rse-share-card">
                    <label for="rse-share-qr-url" style="display: block; font-size: 0.875rem; font-weight: 700; margin-bottom: 0.25rem;">
                        Link QR Code
                    </label>
                    <p style="font-size: 0.75rem; color: var(--rse-s-text-secondary); margin: 0 0 0.625rem;">
                        Otomatis tersemat pada gambar QR Code untuk membedakan pemindaian cetak. Tercatat sebagai sumber <code>qr_barcode</code>.
                    </p>
                    <input
                        id="rse-share-qr-url"
                        x-ref="qrUrlInput"
                        type="text"
                        readonly
                        class="rse-share-input"
                        value="{{ $qrUrl }}"
                        aria-label="Link QR Code"
                        x-on:click="$el.select()"
                        x-on:focus="$el.select()"
                    />
                    <div class="rse-share-actions">
                        <button
                            type="button"
                            class="rse-share-btn rse-share-btn-primary"
                            x-on:click="copyLink('qr', $refs.qrUrlInput, $el)"
                        >
                            <span x-text="copiedKey === 'qr' ? 'Tersalin' : 'Salin Link QR'">Salin Link QR</span>
                        </button>
                        <a
                            href="{{ $qrUrl }}"
                            target="_blank"
                            rel="noopener noreferrer"
                            class="rse-share-btn"
                        >
                            Buka Tautan
                        </a>
                    </div>
                    <p
                        x-show="errorKey === 'qr'"
                        x-cloak
                        role="alert"
                        style="font-size: 0.75rem; color: var(--rse-s-warn-text); margin: 0.5rem 0 0; font-weight: 600;"
                    >
                        Penyalinan otomatis tidak tersedia. Pilih teks pada kolom di atas lalu tekan Ctrl+C atau Cmd+C.
                    </p>
                </div>
            </div>

            <div class="rse-share-qr-panel">
                <p style="font-size: 0.875rem; font-weight: 700; margin: 0;">
                    Pratinjau QR Code
                </p>
                <p style="font-size: 0.75rem; color: var(--rse-s-text-secondary); margin: 0.25rem 0 0;">
                    Mengarah ke tautan <code>?source=qr_barcode</code>
                </p>

                <div class="rse-share-qr-frame">
                    <img
                        src="{{ $qrPreviewDataUri }}"
                        alt="QR Code untuk {{ $formTitle }}"
                        width="192"
                        height="192"
                        style="display: block; width: 192px; max-width: 100%; height: auto;"
                    />
                </div>

                <p style="font-size: 0.75rem; color: var(--rse-s-text-secondary); margin: 0;">
                    Koreksi kesalahan Level M, Quiet Zone 4. Gunakan tombol unduhan di bagian bawah untuk berkas cetak PNG (~1000px) atau vektor SVG.
                </p>
            </div>
        </div>
    @endif
</div>
