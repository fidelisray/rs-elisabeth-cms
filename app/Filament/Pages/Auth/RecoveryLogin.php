<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Services\RecoveryCodeService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\SimplePage;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Timebox;
use Illuminate\Validation\ValidationException;

/**
 * Halaman login darurat menggunakan kode pemulihan sekali pakai khusus super_admin (MVP-1.5).
 *
 * Route didaftarkan secara eksplisit sebagai guest route di AdminPanelProvider,
 * sehingga $isDiscovered disetel false agar tidak masuk ke grup authMiddleware.
 *
 * @property Schema $form
 */
class RecoveryLogin extends SimplePage
{
    public const INVALID_CREDENTIALS_MESSAGE = 'Email atau kode pemulihan tidak valid.';

    public const MAX_ATTEMPTS = 5;

    public const DECAY_SECONDS = 900;

    protected static bool $isDiscovered = false;

    protected string $view = 'filament.pages.auth.recovery-login';

    /** @var array<string, mixed> */
    public ?array $data = [];

    /**
     * Dummy bcrypt hash untuk menjaga waktu eksekusi tetap seragam saat email tidak ditemukan.
     */
    private const DUMMY_HASH = '$2y$04$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG';

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $this->redirect(Filament::getUrl());

            return;
        }

        $this->form->fill();
    }

    public function getTitle(): string
    {
        return 'Pemulihan Akses Super Admin';
    }

    public function getHeading(): string
    {
        return 'Pemulihan Akses Super Admin';
    }

    public function getSubheading(): ?string
    {
        return 'Masukkan email dan salah satu kode pemulihan sekali pakai Anda.';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('email')
                    ->label('Alamat Email')
                    ->email()
                    ->required()
                    ->autocomplete('username')
                    ->autofocus(),

                TextInput::make('recovery_code')
                    ->label('Kode Pemulihan')
                    ->required()
                    ->placeholder('XXXX-XXXX-XXXX-XXXX')
                    ->autocomplete('one-time-code'),
            ])
            ->statePath('data');
    }

    public function authenticate(RecoveryCodeService $recoveryCodes): void
    {
        $data = $this->form->getState();
        $email = strtolower(trim((string) ($data['email'] ?? '')));
        $code = (string) ($data['recovery_code'] ?? '');
        $throttleKey = $this->throttleKey($email);

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            Notification::make()
                ->danger()
                ->title('Terlalu banyak percobaan pemulihan')
                ->body("Silakan coba kembali dalam {$seconds} detik.")
                ->send();

            throw ValidationException::withMessages([
                'data.email' => "Terlalu banyak percobaan pemulihan. Silakan coba kembali dalam {$seconds} detik.",
            ]);
        }

        app(Timebox::class)->call(function (Timebox $timebox) use ($email, $code, $throttleKey, $recoveryCodes): void {
            $user = User::where('email', $email)->first();

            if (! $user || ! $user->is_active || ! $user->isSuperAdmin()) {
                Hash::check($recoveryCodes->normalize($code), self::DUMMY_HASH);
                RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

                throw ValidationException::withMessages([
                    'data.email' => self::INVALID_CREDENTIALS_MESSAGE,
                ]);
            }

            if (! $recoveryCodes->verifyAndConsume($user, $code)) {
                RateLimiter::hit($throttleKey, self::DECAY_SECONDS);

                throw ValidationException::withMessages([
                    'data.email' => self::INVALID_CREDENTIALS_MESSAGE,
                ]);
            }

            $timebox->returnEarly();

            RateLimiter::clear($throttleKey);
            Filament::auth()->login($user);
            Session::regenerate();

            $this->redirect(route('filament.admin.pages.force-password-change'));
        }, 300 * 1000);
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('authenticate')
                ->label('Pulihkan Akses')
                ->submit('authenticate'),
        ];
    }

    private function throttleKey(string $email): string
    {
        return 'recovery-login:'.$email.'|'.request()->ip();
    }
}
