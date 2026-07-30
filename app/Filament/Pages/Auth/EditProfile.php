<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

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
}
