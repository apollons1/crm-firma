<?php

namespace App\Filament\Resources\Opportunities\Pages;

use App\Filament\Resources\Opportunities\OpportunityResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOpportunities extends ListRecords
{
    protected static string $resource = OpportunityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportAll')
                ->label('Export tot')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->url(route('opportunities.export'))
                ->openUrlInNewTab(),
            CreateAction::make(),
        ];
    }
}
