<x-filament-panels::page>
    <style>
        .rse-pw-root {
            --rse-pw-surface: #ffffff;
            --rse-pw-surface-muted: #f8fafc;
            --rse-pw-border: #cbd5e1;
            --rse-pw-border-subtle: #e2e8f0;
            --rse-pw-text: #0f172a;
            --rse-pw-text-secondary: #475569;
            --rse-pw-track: #e2e8f0;

            --rse-pw-rose-fill: #e11d48;
            --rse-pw-rose-bg: #ffe4e6;
            --rse-pw-rose-surface: #fff1f2;
            --rse-pw-rose-border: #fda4af;
            --rse-pw-rose-text: #9f1239;

            --rse-pw-amber-fill: #d97706;
            --rse-pw-amber-bg: #fef3c7;
            --rse-pw-amber-border: #fcd34d;
            --rse-pw-amber-text: #92400e;

            --rse-pw-sky-fill: #0284c7;
            --rse-pw-sky-bg: #e0f2fe;
            --rse-pw-sky-border: #7dd3fc;
            --rse-pw-sky-text: #075985;

            --rse-pw-emerald-fill: #059669;
            --rse-pw-emerald-bg: #d1fae5;
            --rse-pw-emerald-surface: #ecfdf5;
            --rse-pw-emerald-border: #6ee7b7;
            --rse-pw-emerald-text: #065f46;

            max-width: 42rem;
            padding-bottom: 2rem;
            color: var(--rse-pw-text);
            font-variant-numeric: tabular-nums;
        }

        .dark .rse-pw-root {
            --rse-pw-surface: #18181b;
            --rse-pw-surface-muted: #0f172a;
            --rse-pw-border: #3f3f46;
            --rse-pw-border-subtle: #27272a;
            --rse-pw-text: #f8fafc;
            --rse-pw-text-secondary: #cbd5e1;
            --rse-pw-track: #27272a;

            --rse-pw-rose-fill: #f43f5e;
            --rse-pw-rose-bg: #4c0519;
            --rse-pw-rose-surface: #2d0612;
            --rse-pw-rose-border: #be123c;
            --rse-pw-rose-text: #fecdd3;

            --rse-pw-amber-fill: #f59e0b;
            --rse-pw-amber-bg: #451a03;
            --rse-pw-amber-border: #b45309;
            --rse-pw-amber-text: #fde68a;

            --rse-pw-sky-fill: #38bdf8;
            --rse-pw-sky-bg: #082f49;
            --rse-pw-sky-border: #0369a1;
            --rse-pw-sky-text: #bae6fd;

            --rse-pw-emerald-fill: #10b981;
            --rse-pw-emerald-bg: #064e3b;
            --rse-pw-emerald-surface: #042f2e;
            --rse-pw-emerald-border: #047857;
            --rse-pw-emerald-text: #a7f3d0;
        }

        .rse-pw-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .rse-pw-actions {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 0.875rem;
            margin-top: 0.25rem;
            padding-top: 0.25rem;
        }

        /* Indikator Kekuatan Password */
        .rse-pw-strength-card {
            margin-top: 0.5rem;
            padding: 1rem 1.125rem;
            border-radius: 0.75rem;
            background-color: var(--rse-pw-surface-muted);
            border: 1px solid var(--rse-pw-border);
        }

        .rse-pw-strength-head {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 0.5rem;
            margin-bottom: 0.625rem;
        }

        .rse-pw-strength-title {
            font-size: 0.8125rem;
            font-weight: 700;
            color: var(--rse-pw-text);
        }

        .rse-pw-strength-meta {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .rse-pw-strength-ratio {
            font-size: 0.75rem;
            font-weight: 600;
            color: var(--rse-pw-text-secondary);
        }

        .rse-pw-strength-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.1875rem 0.625rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 700;
            border: 1px solid transparent;
            transition: background-color 200ms ease, color 200ms ease, border-color 200ms ease;
        }

        .rse-pw-badge-weak {
            background-color: var(--rse-pw-rose-bg);
            color: var(--rse-pw-rose-text);
            border-color: var(--rse-pw-rose-border);
        }

        .rse-pw-badge-medium {
            background-color: var(--rse-pw-amber-bg);
            color: var(--rse-pw-amber-text);
            border-color: var(--rse-pw-amber-border);
        }

        .rse-pw-badge-strong {
            background-color: var(--rse-pw-sky-bg);
            color: var(--rse-pw-sky-text);
            border-color: var(--rse-pw-sky-border);
        }

        .rse-pw-badge-very-strong {
            background-color: var(--rse-pw-emerald-bg);
            color: var(--rse-pw-emerald-text);
            border-color: var(--rse-pw-emerald-border);
        }

        .rse-pw-bar-track {
            width: 100%;
            height: 0.625rem;
            border-radius: 9999px;
            background-color: var(--rse-pw-track);
            overflow: hidden;
            margin-bottom: 0.875rem;
        }

        .rse-pw-bar-fill {
            height: 100%;
            border-radius: 9999px;
            transition: width 260ms ease, background-color 260ms ease;
        }

        .rse-pw-fill-weak {
            background-color: var(--rse-pw-rose-fill);
        }

        .rse-pw-fill-medium {
            background-color: var(--rse-pw-amber-fill);
        }

        .rse-pw-fill-strong {
            background-color: var(--rse-pw-sky-fill);
        }

        .rse-pw-fill-very-strong {
            background-color: var(--rse-pw-emerald-fill);
        }

        .rse-pw-criteria-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 0.5rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--rse-pw-border-subtle);
        }

        @media (min-width: 540px) {
            .rse-pw-criteria-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .rse-pw-criterion {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.4375rem 0.625rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            line-height: 1.25rem;
            background-color: var(--rse-pw-surface);
            border: 1px solid var(--rse-pw-border-subtle);
            color: var(--rse-pw-text-secondary);
            transition: background-color 200ms ease, border-color 200ms ease, color 200ms ease;
        }

        .rse-pw-criterion-valid {
            background-color: var(--rse-pw-emerald-surface);
            border-color: var(--rse-pw-emerald-border);
            color: var(--rse-pw-emerald-text);
            font-weight: 600;
        }

        /* Callout Kesesuaian Password */
        .rse-pw-match-wrap {
            margin-top: 0.5rem;
        }

        .rse-pw-match-card {
            display: flex;
            align-items: flex-start;
            gap: 0.75rem;
            padding: 0.75rem 1rem;
            border-radius: 0.625rem;
            border: 1px solid transparent;
        }

        .rse-pw-match-ok {
            background-color: var(--rse-pw-emerald-surface);
            border-color: var(--rse-pw-emerald-border);
            color: var(--rse-pw-emerald-text);
        }

        .rse-pw-match-err {
            background-color: var(--rse-pw-rose-surface);
            border-color: var(--rse-pw-rose-border);
            color: var(--rse-pw-rose-text);
        }

        .rse-pw-match-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 1.5rem;
            height: 1.5rem;
            min-width: 1.5rem;
            border-radius: 9999px;
            margin-top: 0.0625rem;
        }

        .rse-pw-match-ok .rse-pw-match-icon {
            background-color: var(--rse-pw-emerald-bg);
            color: var(--rse-pw-emerald-text);
        }

        .rse-pw-match-err .rse-pw-match-icon {
            background-color: var(--rse-pw-rose-bg);
            color: var(--rse-pw-rose-text);
        }

        .rse-pw-match-title {
            font-size: 0.8125rem;
            font-weight: 700;
            line-height: 1.25rem;
        }

        .rse-pw-match-desc {
            font-size: 0.75rem;
            line-height: 1.15rem;
            margin-top: 0.125rem;
            opacity: 0.92;
        }
    </style>

    <div class="rse-pw-root">
        <div x-data="{
            password: '',
            confirmation: '',
            get lengthValid() {
                return this.password.length >= 8;
            },
            get hasUpperAndLower() {
                return /[a-z]/.test(this.password) && /[A-Z]/.test(this.password);
            },
            get hasNumber() {
                return /[0-9]/.test(this.password);
            },
            get hasSpecial() {
                return /[^a-zA-Z0-9]/.test(this.password);
            },
            get score() {
                if (!this.password) return 0;
                let count = 0;
                if (this.lengthValid) count++;
                if (this.hasUpperAndLower) count++;
                if (this.hasNumber) count++;
                if (this.hasSpecial) count++;
                return count;
            },
            get label() {
                if (!this.password) return '';
                if (this.score <= 1) return 'Sangat Lemah';
                if (this.score === 2) return 'Sedang';
                if (this.score === 3) return 'Kuat';
                if (this.score === 4) return 'Sangat Kuat';
                return 'Sangat Lemah';
            },
            debounceTimer: null,
            clearFieldError(field, inputEl = null) {
                const clearWrp = (wrp) => {
                    if (!wrp) return;
                    wrp.querySelectorAll('[data-validation-error]').forEach((el) => el.remove());
                    wrp.querySelector('.fi-input-wrp')?.classList.remove('fi-invalid');
                };

                if (inputEl) {
                    clearWrp(inputEl.closest('[data-field-wrapper]'));
                }

                const clearById = (id) => {
                    const el = document.getElementById(id);
                    if (el) {
                        clearWrp(el.closest('[data-field-wrapper]'));
                    }
                };

                if (field === 'password') {
                    clearById('data.password');
                    clearById('data.passwordConfirmation');
                } else if (field === 'passwordConfirmation') {
                    clearById('data.passwordConfirmation');
                }
            },
            handlePasswordInput(event) {
                this.password = event.target.value;
                this.clearFieldError('password', event.target);
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => {
                    $wire.clearValidationFor('password');
                }, 400);
            },
            handleConfirmationInput(event) {
                this.confirmation = event.target.value;
                this.clearFieldError('passwordConfirmation', event.target);
                clearTimeout(this.debounceTimer);
                this.debounceTimer = setTimeout(() => {
                    $wire.clearValidationFor('passwordConfirmation');
                }, 400);
            }
        }">
            <form wire:submit="save" class="rse-pw-form">
                {{ $this->form }}

                <div class="rse-pw-actions">
                    @foreach ($this->getFormActions() as $action)
                        {{ $action }}
                    @endforeach
                </div>
            </form>
        </div>
    </div>

    @if (Auth::user()?->must_change_password)
        <form id="force-logout-form" method="POST" action="{{ route('filament.admin.auth.logout') }}" class="hidden">
            @csrf
        </form>
    @endif
</x-filament-panels::page>
