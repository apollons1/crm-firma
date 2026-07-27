<?php

namespace App\Filament\Pages;

use App\Models\GoogleToken;
use Filament\Actions\Action;
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
        return [
            Action::make('reconnect')
                ->label($this->googleToken() ? 'Reconectează cont Gmail' : 'Conectează cont Gmail')
                ->icon('heroicon-o-arrow-path')
                ->url('/oauth/google/redirect'),
        ];
    }
}
