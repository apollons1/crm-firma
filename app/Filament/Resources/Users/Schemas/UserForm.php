<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Rules\StrongPassword;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserForm
{
    private const PASSWORD_POLICY_HELP = 'Minim 12 caractere, literă mare + literă mică, '
        .'cel puțin 2 cifre, cel puțin un caracter special. Nu poate conține numele sau '
        .'emailul userului și nu poate fi una dintre ultimele 5 parole folosite.';

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nume')
                    ->required()
                    ->maxLength(255),

                TextInput::make('email')
                    ->label('Email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),

                TextInput::make('password')
                    ->label('Parolă')
                    ->password()
                    ->revealable()
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->rule(fn (?User $record) => new StrongPassword($record))
                    ->rule(Password::defaults())
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->helperText(fn (string $operation): string => $operation === 'edit'
                        ? 'Lasă gol pentru a păstra parola curentă. '.self::PASSWORD_POLICY_HELP
                        : self::PASSWORD_POLICY_HELP
                    ),

                Select::make('roles')
                    ->label('Roluri')
                    ->relationship('roles', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->getOptionLabelFromRecordUsing(
                        fn (Role $record) => str($record->name)->headline()->toString()
                    ),
            ]);
    }
}
