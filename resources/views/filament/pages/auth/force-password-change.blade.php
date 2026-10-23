<x-filament-panels::page>
    <div class="max-w-2xl">
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
        }" class="space-y-6">
            <form wire:submit="save" class="space-y-6">
                {{ $this->form }}

                <div class="flex items-center gap-3">
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
