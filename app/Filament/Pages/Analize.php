<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Analize extends Page
{
    protected static string|\BackedEnum|null $navigationIcon  = 'heroicon-o-chart-bar';
    protected static string|\UnitEnum|null  $navigationGroup = 'Rapoarte';
    protected static ?string               $navigationLabel = 'Analize avansate';
    protected static ?string               $title           = 'Analize avansate';
    protected static ?string               $slug            = 'analize';
    protected static ?int                  $navigationSort  = 1;

    protected string $view = 'filament.pages.analize';
}
