<?php

namespace App\Filament\Resources\Opportunities\Pages;

use App\Filament\Resources\Opportunities\Actions\SendEmailAction;
use App\Filament\Resources\Opportunities\Actions\SendWhatsAppAction;
use App\Filament\Resources\Opportunities\OpportunityResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOpportunity extends EditRecord
{
    protected static string $resource = OpportunityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SendEmailAction::make(),
            SendWhatsAppAction::make(),
            DeleteAction::make(),
        ];
    }
}
