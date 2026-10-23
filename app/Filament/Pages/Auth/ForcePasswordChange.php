<?php

namespace App\Filament\Pages\Auth;

use App\Models\AuditLog;
use App\Rules\StrongNewPassword;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

/**
 * Halaman ganti password wajib (K-8).
 *
 * Halaman terpisah dan penuh. Akses ke halaman lain dijaga di SERVER oleh
 * App\Http\Middleware\EnforcePasswordChange, bukan oleh tampilan browser.
 *
 * @property Schema $form
 */
class ForcePasswordChange extends Page
{
    protected string $view = 'filament.pages.auth.force-password-change';

    protected static ?string $slug = 'force-password-change';

    protected static bool $shouldRegisterNavigation = false;

    protected static bool $isDiscovered = true;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function getTitle(): string
    {
        return Auth::user()?->must_change_password ? 'Ganti Password Wajib' : 'Ganti Password';
    }

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function canAccess(): bool
    {
        return Auth::check();
    }

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        $user = Auth::user();
        $isForced = (bool) $user?->must_change_password;

        $sectionHeading = $isForced
            ? 'Anda harus mengganti password'
            : 'Perbarui Password Akun';

        $sectionDescription = $isForced
            ? 'Demi keamanan, password awal harus diganti sebelum Anda dapat memakai panel. Pilih password baru yang hanya Anda ketahui.'
            : 'Pilih password baru yang kuat untuk menjaga keamanan akun Anda.';

        return $schema
            ->components([
                Section::make($sectionHeading)
                    ->description($sectionDescription)
                    ->schema([
                        TextInput::make('password')
                            ->label('Password Baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(new StrongNewPassword($user?->password))
                            ->extraInputAttributes([
                                'x-on:input' => 'handlePasswordInput($event)',
                            ]),

                        View::make('filament.pages.auth.password-strength-indicator'),

                        TextInput::make('passwordConfirmation')
                            ->label('Ulangi Password Baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->same('password')
                            ->dehydrated(false)
                            ->extraInputAttributes([
                                'x-on:input' => 'handleConfirmationInput($event)',
                            ]),

                        View::make('filament.pages.auth.password-match-indicator'),
                    ]),
            ])
            ->statePath('data');
    }

    public function clearValidationFor(string $field = 'password'): void
    {
        if ($field === 'password') {
            $this->resetErrorBag(['data.password', 'data.passwordConfirmation']);
        } else {
            $this->resetErrorBag(['data.passwordConfirmation']);
        }
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $user = Auth::user();
        $wasForced = (bool) $user?->must_change_password;

        $user->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
        ])->save();

        if ($user->isSuperAdmin()) {
            AuditLog::record('super_admin.password_changed', $user, [
                'source' => $wasForced ? 'force-password-change' : 'profile',
            ]);
        }

        // Sesi lain dari akun yang sama dibatalkan setelah password berubah.
        Auth::logoutOtherDevices($data['password']);
        Session::regenerate();

        Notification::make()
            ->success()
            ->title('Password berhasil diganti')
            ->send();

        $this->redirect($wasForced ? Filament::getUrl() : route('filament.admin.auth.profile'));
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        $isForced = (bool) Auth::user()?->must_change_password;

        $actions = [
            Action::make('save')
                ->label('Simpan Password Baru')
                ->submit('save'),
        ];

        if ($isForced) {
            $actions[] = Action::make('logout')
                ->label('Keluar')
                ->color('gray')
                ->url(route('filament.admin.auth.logout'))
                ->extraAttributes(['onclick' => "event.preventDefault(); document.getElementById('force-logout-form').submit();"]);
        } else {
            $actions[] = Action::make('cancel')
                ->label('Batal / Kembali ke Profil')
                ->color('gray')
                ->url(route('filament.admin.auth.profile'));
        }

        return $actions;
    }

    public function getHeading(): string
    {
        return Auth::user()?->must_change_password ? 'Ganti Password Wajib' : 'Ganti Password';
    }
}
