<?php

namespace App\Filament\Resources\WhatsappTemplates;

use App\Filament\Resources\WhatsappTemplates\Pages\CreateWhatsappTemplate;
use App\Filament\Resources\WhatsappTemplates\Pages\EditWhatsappTemplate;
use App\Filament\Resources\WhatsappTemplates\Pages\ListWhatsappTemplates;
use App\Filament\Resources\WhatsappTemplates\Schemas\WhatsappTemplateForm;
use App\Filament\Resources\WhatsappTemplates\Tables\WhatsappTemplatesTable;
use App\Models\WhatsappTemplate;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class WhatsappTemplateResource extends Resource
{
    protected static ?string $model = WhatsappTemplate::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-document-text';

    protected static string|\UnitEnum|null $navigationGroup = 'Setări';

    protected static ?string $modelLabel = 'Template WhatsApp';

    protected static ?string $pluralModelLabel = 'Template-uri WhatsApp';

    protected static ?string $navigationLabel = 'Template-uri WhatsApp';

    protected static ?string $slug = 'whatsapp-templates';

    /**
     * Evidența template-urilor e sensibilă (Content SID-uri Twilio, folosite
     * direct la trimitere) — restricționăm accesul, la fel ca la Setări Gmail,
     * fără să trecem prin Shield (ar risca să regenereze alte policy-uri).
     */
    public static function canAccess(): bool
    {
        return auth()->user()?->hasAnyRole(['super_admin', 'admin']) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return WhatsappTemplateForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return WhatsappTemplatesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListWhatsappTemplates::route('/'),
            'create' => CreateWhatsappTemplate::route('/create'),
            'edit' => EditWhatsappTemplate::route('/{record}/edit'),
        ];
    }
}
