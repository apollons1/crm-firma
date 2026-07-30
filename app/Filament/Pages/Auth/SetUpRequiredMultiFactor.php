<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Auth\MultiFactor\Contracts\MultiFactorAuthenticationProvider;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Copie a paginii native Filament\Auth\MultiFactor\Pages\SetUpRequiredMultiFactorAuthentication,
 * înregistrată explicit ca pagină normală (nu prin mecanismul intern al lui
 * Filament, care înregistrează ruta doar dacă isRequired evaluează true la
 * BOOT-ul aplicației — moment în care auth()->user() e mereu null, deci un
 * closure bazat pe rol nu poate funcționa niciodată acolo). Impunerea
 * efectivă (doar pentru super_admin/admin) se face din
 * EnsureRequiredMultiFactorAuthenticationIsEnabled (authMiddleware),
 * evaluat corect la fiecare cerere, cu userul autentificat disponibil.
 */
class SetUpRequiredMultiFactor extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'configurare-2fa-obligatorie';

    protected static ?string $title = 'Configurare autentificare cu doi factori';

    public function getSubheading(): ?string
    {
        return 'Pentru securitate, configurează 2FA înainte să continui.';
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getMultiFactorAuthenticationContentComponent(),
                Actions::make([$this->getContinueAction()])->fullWidth(),
            ]);
    }

    public function getMultiFactorAuthenticationContentComponent(): Component
    {
        $user = Filament::auth()->user();

        return Section::make()
            ->compact()
            ->divided()
            ->secondary()
            ->schema(collect(Filament::getMultiFactorAuthenticationProviders())
                ->sort(fn (MultiFactorAuthenticationProvider $provider): int => $provider->isEnabled($user) ? 0 : 1)
                ->map(fn (MultiFactorAuthenticationProvider $provider): Component => Group::make($provider->getManagementSchemaComponents())
                    ->statePath($provider->getId()))
                ->all());
    }

    public function getContinueAction(): Action
    {
        return Action::make('continue')
            ->label('Continuă')
            ->action(fn () => redirect()->intended(Filament::getUrl()))
            ->visible($this->isEnabled(...));
    }

    public function isEnabled(): bool
    {
        $user = Filament::auth()->user();

        foreach (Filament::getMultiFactorAuthenticationProviders() as $provider) {
            if ($provider->isEnabled($user)) {
                return true;
            }
        }

        return false;
    }

    public function hasLogo(): bool
    {
        return false;
    }
}
