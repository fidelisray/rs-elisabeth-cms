<div class="rse-preview-root">
    <style>
        .rse-preview-root {
            --rse-p-surface: var(--rse-slate-surface, #ffffff);
            --rse-p-muted: var(--rse-slate-muted, #f8fafc);
            --rse-p-border: var(--rse-slate-border-strong, #64748b);
            --rse-p-divider: var(--rse-slate-border, #cbd5e1);
            --rse-p-text: var(--rse-slate-text, #0f172a);
            --rse-p-text-secondary: var(--rse-slate-text-secondary, #475569);
            --rse-p-accent: var(--rse-brand-accent, #095988);
            --rse-p-accent-hover: var(--rse-brand-accent-hover, #07476e);
            --rse-p-accent-text: var(--rse-brand-accent-text, #ffffff);
            --rse-p-link: var(--rse-brand-link, #095988);
            --rse-p-badge-bg: var(--rse-brand-badge-bg, #dceff9);
            --rse-p-badge-text: var(--rse-brand-badge-text, #07476e);
            --rse-p-err-bg: #ffe4e6;
            --rse-p-err-text: #9f1239;
            --rse-p-ok-bg: #d1fae5;
            --rse-p-ok-text: #094d38;
            color: var(--rse-p-text);
            font-variant-numeric: tabular-nums;
        }

        .dark .rse-preview-root {
            --rse-p-surface: var(--rse-slate-surface, #0f172a);
            --rse-p-muted: var(--rse-slate-muted, #1e293b);
            --rse-p-border: var(--rse-slate-border-strong, #64748b);
            --rse-p-divider: var(--rse-slate-border, #334155);
            --rse-p-text: var(--rse-slate-text, #f8fafc);
            --rse-p-text-secondary: var(--rse-slate-text-secondary, #cbd5e1);
            --rse-p-accent: var(--rse-brand-accent, #1997e6);
            --rse-p-accent-hover: var(--rse-brand-accent-hover, #89c9ea);
            --rse-p-accent-text: var(--rse-brand-accent-text, #0b0f19);
            --rse-p-link: var(--rse-brand-link, #38bdf8);
            --rse-p-badge-bg: var(--rse-brand-badge-bg, #052d47);
            --rse-p-badge-text: var(--rse-brand-badge-text, #bae0f4);
            --rse-p-err-bg: #4c0519;
            --rse-p-err-text: #fecdd3;
            --rse-p-ok-bg: #052e21;
            --rse-p-ok-text: #a7f3d0;
        }

        .rse-preview-banner {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            margin-bottom: 1rem;
            border-radius: 0.5rem;
            background-color: var(--rse-p-muted);
            border: 1px solid var(--rse-p-divider);
        }

        .rse-preview-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.25rem 0.625rem;
            border-radius: 0.375rem;
            background-color: var(--rse-p-badge-bg);
            color: var(--rse-p-badge-text);
            font-size: 0.75rem;
            font-weight: 700;
        }

        .rse-preview-card {
            padding: 1.25rem;
            border-radius: 0.625rem;
            background-color: var(--rse-p-surface);
            border: 1px solid var(--rse-p-divider);
        }

        .rse-preview-field {
            padding: 1rem;
            border-radius: 0.5rem;
            background-color: var(--rse-p-muted);
            border: 1px solid var(--rse-p-divider);
            margin-bottom: 0.875rem;
        }

        .rse-preview-input {
            width: 100%;
            min-height: 44px;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            border: 1px solid var(--rse-p-border);
            background-color: var(--rse-p-surface);
            color: var(--rse-p-text);
            font-size: 0.875rem;
        }

        .rse-preview-input:focus-visible,
        .rse-preview-btn:focus-visible,
        .rse-preview-choice:focus-within,
        .rse-preview-scale-btn:focus-within {
            outline: 2px solid var(--rse-p-accent);
            outline-offset: 2px;
        }

        .rse-preview-choice {
            display: flex;
            align-items: center;
            gap: 0.625rem;
            min-height: 44px;
            padding: 0.5rem 0.75rem;
            border-radius: 0.375rem;
            border: 1px solid var(--rse-p-divider);
            background-color: var(--rse-p-surface);
            cursor: pointer;
            margin-bottom: 0.5rem;
        }

        .rse-preview-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid var(--rse-p-border);
            background-color: var(--rse-p-surface);
            color: var(--rse-p-text);
        }

        .rse-preview-btn-primary {
            background-color: var(--rse-p-accent);
            border-color: var(--rse-p-accent);
            color: var(--rse-p-accent-text);
        }

        .rse-preview-btn-primary:hover {
            background-color: var(--rse-p-accent-hover);
        }

        .rse-preview-matrix-wrap {
            overflow-x: auto;
            border: 1px solid var(--rse-p-divider);
            border-radius: 0.5rem;
            background-color: var(--rse-p-surface);
        }

        .rse-preview-matrix {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }

        .rse-preview-matrix th,
        .rse-preview-matrix td {
            padding: 0.625rem 0.75rem;
            border-bottom: 1px solid var(--rse-p-divider);
            text-align: center;
        }

        .rse-preview-matrix th:first-child,
        .rse-preview-matrix td:first-child {
            text-align: left;
            font-weight: 600;
        }

        .rse-preview-scale-row {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .rse-preview-scale-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 44px;
            min-height: 44px;
            padding: 0 0.75rem;
            border-radius: 0.375rem;
            border: 1px solid var(--rse-p-border);
            background-color: var(--rse-p-surface);
            color: var(--rse-p-text);
            font-weight: 600;
            cursor: pointer;
        }

        .rse-p-rich-text {
            font-size: 0.875rem;
            line-height: 1.6;
            color: var(--rse-p-text-secondary);
            overflow-wrap: anywhere;
        }

        .rse-p-rich-text p { margin: 0 0 0.5rem; }
        .rse-p-rich-text li > p { margin: 0; }
        .rse-p-rich-text p:last-child { margin-bottom: 0; }
        .rse-p-rich-text h2, .rse-p-rich-text h3 { color: var(--rse-p-text); font-weight: 700; margin: 0.625rem 0 0.375rem; }
        .rse-p-rich-text h2 { font-size: 1rem; }
        .rse-p-rich-text h3 { font-size: 0.9375rem; }
        .rse-p-rich-text ul, .rse-p-rich-text ol { margin: 0.375rem 0 0.5rem; padding-left: 1.25rem; }
        .rse-p-rich-text ul { list-style-type: disc; }
        .rse-p-rich-text ol { list-style-type: decimal; }
        .rse-p-rich-text blockquote { margin: 0.5rem 0; padding: 0.375rem 0.75rem; border-left: 3px solid var(--rse-p-accent); background-color: var(--rse-p-muted); }
        .rse-p-rich-text a { color: var(--rse-p-link); text-decoration: underline; }
    </style>

    <div class="rse-preview-banner">
        <div>
            <p style="font-size: 0.9375rem; font-weight: 700; margin: 0;">{{ $formTitle }}</p>
            <p style="font-size: 0.75rem; color: var(--rse-p-text-secondary); margin: 0.125rem 0 0;">
                Mode simulasi: jawaban pratinjau tidak disimpan ke database.
            </p>
        </div>
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 0.5rem;">
            <span class="rse-preview-badge">{{ $sourceLabel }}</span>
            <button type="button" wire:click="resetPreview" class="rse-preview-btn">
                Ulangi Pratinjau
            </button>
        </div>
    </div>

    @if (! empty($formDescription))
        <div class="rse-preview-card rse-p-rich-text" style="margin-bottom: 1rem; background-color: var(--rse-p-muted);">
            {!! app(\App\Services\DynamicForm\DynamicFormHtmlSanitizer::class)->sanitize($formDescription) !!}
        </div>
    @endif

    <div wire:loading.flex wire:target="nextSection,previousSection,resetPreview" style="align-items: center; gap: 0.5rem; padding: 0.625rem 0.875rem; margin-bottom: 0.75rem; border-radius: 0.5rem; background-color: var(--rse-p-muted); border: 1px solid var(--rse-p-divider); font-size: 0.8125rem;">
        <span>Memproses aturan percabangan dan validasi bagian...</span>
    </div>

    @if ($previewError)
        <div role="alert" style="padding: 1rem; border-radius: 0.5rem; background-color: var(--rse-p-err-bg); color: var(--rse-p-err-text); margin-bottom: 1rem;">
            <p style="font-weight: 700; margin: 0 0 0.25rem;">Pratinjau tidak dapat dilanjutkan</p>
            <p style="font-size: 0.875rem; margin: 0;">{{ $previewError }}</p>
        </div>
    @endif

    @if (count($sections) === 0)
        <div class="rse-preview-card" style="text-align: center; padding: 2rem;">
            <p style="font-weight: 700; margin: 0 0 0.375rem;">Belum ada bagian dan pertanyaan pada formulir ini.</p>
            <p style="font-size: 0.875rem; color: var(--rse-p-text-secondary); margin: 0;">
                Tambahkan minimal satu bagian dan pertanyaan pada langkah Section & Pertanyaan untuk menguji alur pratinjau.
            </p>
        </div>
    @elseif ($isCompleted)
        <div class="rse-preview-card">
            <div style="padding: 1rem; border-radius: 0.5rem; background-color: var(--rse-p-ok-bg); color: var(--rse-p-ok-text); margin-bottom: 1rem;">
                <p style="font-weight: 700; margin: 0 0 0.25rem;">Simulasi Pengisian Berhasil</p>
                <p style="font-size: 0.875rem; margin: 0;">
                    Semua validasi dan aturan percabangan terpenuhi tanpa error.
                </p>
            </div>
            <p style="font-size: 0.8125rem; font-weight: 700; margin: 0 0 0.5rem;">Jejak Jawaban Tersanitasi (Payload Simulasi):</p>
            <pre style="padding: 0.875rem; border-radius: 0.5rem; background-color: var(--rse-p-muted); border: 1px solid var(--rse-p-divider); overflow-x: auto; font-size: 0.75rem;">{{ json_encode($evaluation['sanitized_answers'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            <div style="margin-top: 1rem; display: flex; flex-wrap: wrap; gap: 0.75rem;">
                <button type="button" wire:click="previousSection" class="rse-preview-btn">Kembali ke Bagian Terakhir</button>
                <button type="button" wire:click="resetPreview" class="rse-preview-btn rse-preview-btn-primary">Mulai Simulasi Baru</button>
            </div>
        </div>
    @elseif ($currentSection)
        <div class="rse-preview-card">
            <div style="margin-bottom: 1rem; padding-bottom: 0.75rem; border-bottom: 1px solid var(--rse-p-divider);">
                <p style="font-size: 0.75rem; font-weight: 600; color: var(--rse-p-text-secondary); margin: 0 0 0.25rem;">
                    Bagian {{ count($sectionHistory) }}
                </p>
                <h3 style="font-size: 1.0625rem; font-weight: 700; margin: 0;">{{ $currentSection['title'] }}</h3>
                @php
                    $safeSectionDesc = app(\App\Services\DynamicForm\DynamicFormHtmlSanitizer::class)->sanitize($currentSection['description'] ?? null);
                @endphp
                @if ($safeSectionDesc !== null)
                    <div class="rse-p-rich-text" style="margin-top: 0.5rem;">
                        {!! $safeSectionDesc !!}
                    </div>
                @endif
            </div>

            @if (count($visibleFields) === 0)
                <div class="rse-preview-field">
                    <p style="font-size: 0.875rem; color: var(--rse-p-text-secondary); margin: 0;">
                        Tidak ada pertanyaan yang tampil pada bagian ini berdasarkan kondisi jawaban saat ini.
                    </p>
                </div>
            @endif

            @foreach ($visibleFields as $field)
                @php
                    $fKey = (string) $field['field_key'];
                    $fType = (string) $field['type'];
                    $fOpts = (array) ($field['options'] ?? []);
                    $fieldErrors = $validationErrors["answers.{$fKey}"] ?? [];
                @endphp

                <div class="rse-preview-field" wire:key="pv-{{ $currentSection['section_key'] }}-{{ $fKey }}">
                    @if ($fType === 'info_block')
                        <p style="font-size: 0.9375rem; font-weight: 700; margin: 0 0 0.375rem;">{{ $field['label'] }}</p>
                        @if (! empty($fOpts['content']))
                            <div style="font-size: 0.875rem; color: var(--rse-p-text-secondary); line-height: 1.5;">
                                {!! nl2br(e((string) $fOpts['content'])) !!}
                            </div>
                        @endif
                        @php
                            $mediaUrl = (string) ($fOpts['media_url'] ?? '');
                            $mediaScheme = strtolower((string) parse_url($mediaUrl, PHP_URL_SCHEME));
                        @endphp
                        @if ($mediaUrl !== '' && in_array($mediaScheme, ['http', 'https'], true))
                            <div style="margin-top: 0.625rem;">
                                <img src="{{ $mediaUrl }}" alt="{{ $field['label'] }}" style="max-width: 100%; height: auto; border-radius: 0.375rem; border: 1px solid var(--rse-p-divider);" />
                            </div>
                        @endif
                    @else
                        <label for="pv-input-{{ $fKey }}" style="display: block; font-size: 0.875rem; font-weight: 700; margin-bottom: 0.25rem;">
                            {{ $field['label'] }}
                            @if (! empty($field['is_required']))
                                <span style="color: var(--rse-p-err-text);">*</span>
                            @endif
                        </label>
                        @if (! empty($field['help_text']))
                            <p style="font-size: 0.75rem; color: var(--rse-p-text-secondary); margin: 0 0 0.5rem;">
                                {{ $field['help_text'] }}
                            </p>
                        @endif

                        @if ($fType === 'short_text')
                            @php
                                $subtype = (string) ($fOpts['subtype'] ?? 'text');
                                $formatPreset = (string) (($field['validation_rules']['format_preset'] ?? ''));
                                $inputType = match ($subtype) {
                                    'email' => 'email',
                                    'number' => 'number',
                                    'url' => 'url',
                                    'phone' => 'tel',
                                    default => 'text',
                                };
                                $inputMode = match (true) {
                                    $subtype === 'phone' => 'tel',
                                    $subtype === 'text' && $formatPreset === 'digits_only' => 'numeric',
                                    default => null,
                                };
                            @endphp
                            <input
                                id="pv-input-{{ $fKey }}"
                                type="{{ $inputType }}"
                                @if ($inputMode) inputmode="{{ $inputMode }}" @endif
                                class="rse-preview-input"
                                wire:model.live="answers.{{ $fKey }}"
                                placeholder="{{ $field['placeholder'] ?? ($subtype === 'phone' ? 'Contoh: 081234567890' : 'Ketik jawaban Anda') }}"
                            />
                        @elseif ($fType === 'long_text')
                            <textarea
                                id="pv-input-{{ $fKey }}"
                                rows="3"
                                class="rse-preview-input"
                                wire:model.live="answers.{{ $fKey }}"
                                placeholder="{{ $field['placeholder'] ?? 'Ketik uraian jawaban Anda' }}"
                            ></textarea>
                        @elseif ($fType === 'radio')
                            <div>
                                @foreach ((array) ($fOpts['choices'] ?? []) as $choice)
                                    <label class="rse-preview-choice">
                                        <input
                                            type="radio"
                                            name="pv-radio-{{ $fKey }}"
                                            value="{{ $choice['key'] }}"
                                            wire:model.live="answers.{{ $fKey }}.selected"
                                        />
                                        <span style="font-size: 0.875rem;">{{ $choice['label'] }}</span>
                                    </label>
                                @endforeach
                                @if (! empty($fOpts['allow_other']))
                                    <label class="rse-preview-choice">
                                        <input
                                            type="radio"
                                            name="pv-radio-{{ $fKey }}"
                                            value="__other__"
                                            wire:model.live="answers.{{ $fKey }}.selected"
                                        />
                                        <span style="font-size: 0.875rem;">{{ $fOpts['other_label'] ?? 'Lainnya' }}</span>
                                    </label>
                                    @if (($answers[$fKey]['selected'] ?? null) === '__other__')
                                        <input
                                            type="text"
                                            class="rse-preview-input"
                                            style="margin-top: 0.375rem;"
                                            wire:model.live="answers.{{ $fKey }}.other_text"
                                            aria-label="{{ $field['label'] }} - {{ $fOpts['other_label'] ?? 'Lainnya' }}"
                                            placeholder="Tuliskan jawaban lainnya..."
                                        />
                                    @endif
                                @endif
                            </div>
                        @elseif ($fType === 'checkbox')
                            <div>
                                @foreach ((array) ($fOpts['choices'] ?? []) as $choice)
                                    <label class="rse-preview-choice">
                                        <input
                                            type="checkbox"
                                            value="{{ $choice['key'] }}"
                                            wire:model.live="answers.{{ $fKey }}.selected"
                                        />
                                        <span style="font-size: 0.875rem;">{{ $choice['label'] }}</span>
                                    </label>
                                @endforeach
                                @if (! empty($fOpts['allow_other']))
                                    <label class="rse-preview-choice">
                                        <input
                                            type="checkbox"
                                            value="__other__"
                                            wire:model.live="answers.{{ $fKey }}.selected"
                                        />
                                        <span style="font-size: 0.875rem;">{{ $fOpts['other_label'] ?? 'Lainnya' }}</span>
                                    </label>
                                    @if (in_array('__other__', (array) ($answers[$fKey]['selected'] ?? []), true))
                                        <input
                                            type="text"
                                            class="rse-preview-input"
                                            style="margin-top: 0.375rem;"
                                            wire:model.live="answers.{{ $fKey }}.other_text"
                                            aria-label="{{ $field['label'] }} - {{ $fOpts['other_label'] ?? 'Lainnya' }}"
                                            placeholder="Tuliskan jawaban lainnya..."
                                        />
                                    @endif
                                @endif
                            </div>
                        @elseif ($fType === 'dropdown')
                            <select id="pv-input-{{ $fKey }}" class="rse-preview-input" wire:model.live="answers.{{ $fKey }}">
                                <option value="">Pilih salah satu...</option>
                                @foreach ((array) ($fOpts['choices'] ?? []) as $choice)
                                    <option value="{{ $choice['key'] }}">{{ $choice['label'] }}</option>
                                @endforeach
                            </select>
                        @elseif ($fType === 'linear_scale' || $fType === 'rating')
                            @php
                                $minV = $fType === 'rating' ? 1 : (int) ($fOpts['min_value'] ?? 1);
                                $maxV = $fType === 'rating' ? (int) ($fOpts['max_stars'] ?? 5) : (int) ($fOpts['max_value'] ?? 5);
                                $selectedScale = $answers[$fKey] ?? null;
                                $minLabel = $fType === 'linear_scale' ? ($fOpts['min_label'] ?? null) : null;
                                $maxLabel = $fType === 'linear_scale' ? ($fOpts['max_label'] ?? null) : null;
                            @endphp
                            <div class="rse-preview-scale-row">
                                @for ($val = $minV; $val <= $maxV; $val++)
                                    @php $isPicked = (string) $selectedScale === (string) $val; @endphp
                                    <label class="rse-preview-scale-btn" style="{{ $isPicked ? 'background-color: var(--rse-p-accent); border-color: var(--rse-p-accent); color: var(--rse-p-accent-text);' : '' }}">
                                        <input
                                            type="radio"
                                            name="pv-scale-{{ $fKey }}"
                                            value="{{ $val }}"
                                            wire:model.live="answers.{{ $fKey }}"
                                            style="position: absolute; opacity: 0; width: 1px; height: 1px;"
                                        />
                                        <span>{{ $val }}</span>
                                    </label>
                                @endfor
                            </div>
                            @if ($minLabel || $maxLabel)
                                <div style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.5rem; margin-top: 0.375rem; font-size: 0.75rem; color: var(--rse-p-text-secondary);">
                                    <span>{{ $minLabel ? "{$minV}: {$minLabel}" : '' }}</span>
                                    <span>{{ $maxLabel ? "{$maxV}: {$maxLabel}" : '' }}</span>
                                </div>
                            @endif
                        @elseif ($fType === 'matrix_grid')
                            @php
                                $selMode = ($fOpts['selection_mode'] ?? 'single') === 'multiple' ? 'multiple' : 'single';
                                $mRows = (array) ($fOpts['rows'] ?? []);
                                $mCols = (array) ($fOpts['columns'] ?? []);
                            @endphp
                            <div class="rse-preview-matrix-wrap">
                                <table class="rse-preview-matrix">
                                    <thead>
                                        <tr>
                                            <th>Aspek / Butir</th>
                                            @foreach ($mCols as $col)
                                                <th>{{ $col['label'] }}</th>
                                            @endforeach
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($mRows as $mRow)
                                            <tr>
                                                <td>{{ $mRow['label'] }}</td>
                                                @foreach ($mCols as $col)
                                                    <td>
                                                        @if ($selMode === 'single')
                                                            <input
                                                                type="radio"
                                                                name="pv-mx-{{ $fKey }}-{{ $mRow['key'] }}"
                                                                value="{{ $col['key'] }}"
                                                                wire:model.live="answers.{{ $fKey }}.{{ $mRow['key'] }}"
                                                                aria-label="{{ $mRow['label'] }} - {{ $col['label'] }}"
                                                            />
                                                        @else
                                                            <input
                                                                type="checkbox"
                                                                value="{{ $col['key'] }}"
                                                                wire:model.live="answers.{{ $fKey }}.{{ $mRow['key'] }}"
                                                                aria-label="{{ $mRow['label'] }} - {{ $col['label'] }}"
                                                            />
                                                        @endif
                                                    </td>
                                                @endforeach
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @elseif ($fType === 'date')
                            <input id="pv-input-{{ $fKey }}" type="date" class="rse-preview-input" wire:model.live="answers.{{ $fKey }}" />
                        @elseif ($fType === 'time')
                            <input id="pv-input-{{ $fKey }}" type="time" class="rse-preview-input" wire:model.live="answers.{{ $fKey }}" />
                        @elseif ($fType === 'file_upload')
                            <div style="padding: 0.75rem; border-radius: 0.375rem; border: 1px dashed var(--rse-p-border); background-color: var(--rse-p-surface); font-size: 0.8125rem; color: var(--rse-p-text-secondary);">
                                Unggahan berkas (ekstensi: {{ implode(', ', (array) ($fOpts['allowed_extensions'] ?? ['pdf'])) }}, maks. {{ $fOpts['max_file_size_mb'] ?? 5 }} MB) akan diproses via endpoint two-step upload di Web Frontend.
                            </div>
                        @endif

                        @if ($fieldErrors !== [])
                            <div role="alert" style="margin-top: 0.5rem; padding: 0.5rem 0.75rem; border-radius: 0.375rem; background-color: var(--rse-p-err-bg); color: var(--rse-p-err-text); font-size: 0.8125rem; font-weight: 600;">
                                {{ implode(' ', $fieldErrors) }}
                            </div>
                        @endif
                    @endif
                </div>
            @endforeach

            <div style="display: flex; flex-wrap: wrap; justify-content: space-between; gap: 0.75rem; margin-top: 1.25rem; padding-top: 1rem; border-top: 1px solid var(--rse-p-divider);">
                <div>
                    @if (count($sectionHistory) > 1)
                        <button type="button" wire:click="previousSection" class="rse-preview-btn">
                            Bagian Sebelumnya
                        </button>
                    @endif
                </div>
                <button type="button" wire:click="nextSection" class="rse-preview-btn rse-preview-btn-primary">
                    Lanjutkan / Uji Langkah Ini
                </button>
            </div>
        </div>
    @endif
</div>
