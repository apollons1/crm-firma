<?php

namespace App\Filament\Pages\Auth;

use App\Rules\StrongPassword;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;

class EditProfile extends BaseEditProfile
{
    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Profil')
                    ->tabs([
                        Tab::make('Date cont')
                            ->icon('heroicon-o-user')
                            ->schema([
                                $this->getFormContentComponent(),
                            ]),
                        Tab::make('Securitate')
                            ->icon('heroicon-o-shield-check')
                            ->schema(Arr::wrap($this->getMultiFactorAuthenticationContentComponent())),
                    ]),
            ]);
    }

    /**
     * Aceeași politică de parole ca la crearea/editarea userilor din
     * UserResource — altfel un user și-ar putea seta singur o parolă slabă
     * din propriul profil, ocolind complet regulile impuse acolo.
     */
    protected function getPasswordFormComponent(): Component
    {
        return parent::getPasswordFormComponent()
            ->rule(fn () => new StrongPassword($this->getUser()));
    }

    /**
     * Loghează blocarea (fără date sensibile — doar cine și de unde),
     * apoi păstrează mesajul clar (secunde rămase) al Filament.
     */
    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        Log::warning('Schimbare profil/parolă blocată — prea multe încercări.', [
            'user_id' => $this->getUser()->getAuthIdentifier(),
            'ip' => $exception->ip,
            'seconds_remaining' => $exception->secondsUntilAvailable,
        ]);

        return parent::getRateLimitedNotification($exception);
    }
}
