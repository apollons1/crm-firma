<?php

namespace App\Filament\Pages;

use App\Models\GoogleToken;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class GmailSettings extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-envelope';

    protected static string|\UnitEnum|null $navigationGroup = 'Setări';

    protected static ?string $navigationLabel = 'Gmail';

    protected static ?string $title = 'Setări Gmail';

    protected static ?string $slug = 'gmail-settings';

    protected string $view = 'filament.pages.gmail-settings';

    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public function googleToken(): ?GoogleToken
    {
        return GoogleToken::first();
    }

    protected function getHeaderActions(): array
    {
        $token = $this->googleToken();

        return [
            Action::make('reconnect')
                ->label($token ? 'Reconectează cont Gmail' : 'Conectează cont Gmail')
                ->icon('heroicon-o-arrow-path')
                ->url('/oauth/google/redirect'),
            Action::make('toggleAutoAssociate')
                ->visible($token !== null)
                ->label(fn (): string => $this->googleToken()?->auto_associate
                    ? 'Dezactivează asocierea automată'
                    : 'Activează asocierea automată')
                ->icon('heroicon-o-link')
                ->color('gray')
                ->action(function (): void {
                    $token = $this->googleToken();
                    $token->update(['auto_associate' => ! $token->auto_associate]);

                    Notification::make()
                        ->title($token->auto_associate
                            ? 'Asociere automată activată'
                            : 'Asociere automată dezactivată')
                        ->success()
                        ->send();
                }),
            Action::make('toggleMarkAsRead')
                ->visible($token !== null)
                ->label(fn (): string => $this->googleToken()?->mark_as_read
                    ? 'Dezactivează marcarea ca citit'
                    : 'Activează marcarea ca citit')
                ->icon('heroicon-o-envelope-open')
                ->color('gray')
                ->action(function (): void {
                    $token = $this->googleToken();
                    $enabling = ! $token->mark_as_read;
                    $token->update(['mark_as_read' => $enabling]);

                    if ($enabling && ! $token->hasScope('https://www.googleapis.com/auth/gmail.modify')) {
                        Notification::make()
                            ->title('Marcare ca citit activată, dar necesită reconectare')
                            ->body('Contul Gmail conectat nu are permisiunea gmail.modify. Apasă „Reconectează cont Gmail” pentru a o adăuga.')
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title($enabling ? 'Marcare ca citit activată' : 'Marcare ca citit dezactivată')
                        ->success()
                        ->send();
                }),
        ];
    }
}
