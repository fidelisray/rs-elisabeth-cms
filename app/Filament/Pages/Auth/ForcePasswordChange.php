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
        return 'Ganti Password Wajib';
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
        // User yang tidak wajib mengganti password tidak perlu di halaman ini.
        if (! Auth::user()?->must_change_password) {
            $this->redirect(Filament::getUrl());

            return;
        }

        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        $user = Auth::user();

        return $schema
            ->components([
                Section::make('Anda harus mengganti password')
                    ->description('Demi keamanan, password awal harus diganti sebelum Anda dapat memakai panel. Pilih password baru yang hanya Anda ketahui.')
                    ->schema([
                        TextInput::make('password')
                            ->label('Password Baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->rule(new StrongNewPassword($user?->password))
                            ->helperText('Minimal '.StrongNewPassword::MIN_LENGTH.' karakter. Tidak boleh password default atau sama dengan password lama.'),

                        TextInput::make('passwordConfirmation')
                            ->label('Ulangi Password Baru')
                            ->password()
                            ->revealable()
                            ->required()
                            ->same('password')
                            ->dehydrated(false),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        $user = Auth::user();

        $user->forceFill([
            'password' => $data['password'],
            'must_change_password' => false,
        ])->save();

        if ($user->isSuperAdmin()) {
            AuditLog::record('super_admin.password_changed', $user, ['source' => 'force-password-change']);
        }

        // Sesi lain dari akun yang sama dibatalkan setelah password berubah.
        Auth::logoutOtherDevices($data['password']);
        Session::regenerate();

        Notification::make()
            ->success()
            ->title('Password berhasil diganti')
            ->send();

        $this->redirect(Filament::getUrl());
    }

    /**
     * @return array<Action>
     */
    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Simpan Password Baru')
                ->submit('save'),
            Action::make('logout')
                ->label('Keluar')
                ->color('gray')
                ->url(route('filament.admin.auth.logout'))
                ->extraAttributes(['onclick' => "event.preventDefault(); document.getElementById('force-logout-form').submit();"]),
        ];
    }

    public function getHeading(): string
    {
        return 'Ganti Password Wajib';
    }
}
