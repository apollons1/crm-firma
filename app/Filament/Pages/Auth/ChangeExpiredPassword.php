<?php

namespace App\Filament\Pages\Auth;

use App\Models\User;
use App\Rules\StrongPassword;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

/**
 * Pagină forțată (nu apare în navigare) — EnsurePasswordIsNotExpired
 * redirecționează aici orice super_admin/admin cu parola expirată (>90 zile)
 * la fiecare cerere, până își schimbă parola.
 */
class ChangeExpiredPassword extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'parola-expirata';

    protected static ?string $title = 'Parola a expirat';

    protected string $view = 'filament.pages.change-expired-password';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    Text::make(
                        'Din motive de securitate, parola contului tău a expirat (mai vechi de 90 de zile) '.
                        'și trebuie schimbată înainte de a continua.'
                    )->color('warning'),

                    TextInput::make('password')
                        ->label('Parolă nouă')
                        ->password()
                        ->revealable()
                        ->required()
                        ->rule(fn (): StrongPassword => new StrongPassword($this->getUser()))
                        ->rule(Password::defaults())
                        ->same('passwordConfirmation'),

                    TextInput::make('passwordConfirmation')
                        ->label('Confirmă parola nouă')
                        ->password()
                        ->revealable()
                        ->required()
                        ->dehydrated(false),
                ])
                    ->livewireSubmitHandler('changePassword')
                    ->footer([
                        Actions::make([
                            Action::make('changePassword')
                                ->label('Schimbă parola')
                                ->submit('changePassword'),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function changePassword(): void
    {
        $data = $this->form->getState();

        $user = $this->getUser();
        $user->password = $data['password'];
        $user->save();

        Notification::make()
            ->title('Parola a fost schimbată cu succes')
            ->success()
            ->send();

        redirect()->intended(Filament::getUrl());
    }

    protected function getUser(): User
    {
        /** @var User $user */
        $user = Filament::auth()->user();

        return $user;
    }

    public function hasLogo(): bool
    {
        return false;
    }
}
