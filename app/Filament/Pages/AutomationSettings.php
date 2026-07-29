<?php

namespace App\Filament\Pages;

use App\Listeners\SendStuckOpportunityNotification;
use App\Listeners\SendWonNotification;
use App\Models\AutomationSetting;
use App\Models\WhatsappTemplate;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class AutomationSettings extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-bolt';

    protected static string|UnitEnum|null $navigationGroup = 'Setări';

    protected static ?string $navigationLabel = 'Automatizări WhatsApp';

    protected static ?string $title = 'Automatizări WhatsApp';

    protected static ?string $slug = 'automation-settings';

    protected string $view = 'filament.pages.automation-settings';

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'opportunity_won_enabled' => AutomationSetting::get('opportunity_won.enabled', true),
            'opportunity_won_message_template' => AutomationSetting::get(
                'opportunity_won.message_template',
                SendWonNotification::DEFAULT_TEMPLATE
            ),
            'opportunity_won_fallback_template_id' => AutomationSetting::get('opportunity_won.fallback_template_id'),

            'opportunity_stuck_enabled' => AutomationSetting::get('opportunity_stuck.enabled', true),
            'opportunity_stuck_message_template' => AutomationSetting::get(
                'opportunity_stuck.message_template',
                SendStuckOpportunityNotification::DEFAULT_TEMPLATE
            ),
            'opportunity_stuck_fallback_template_id' => AutomationSetting::get('opportunity_stuck.fallback_template_id'),
            'opportunity_stuck_send_hour' => AutomationSetting::get('opportunity_stuck.send_hour', 9),
            'opportunity_stuck_days_lead' => AutomationSetting::get('opportunity_stuck.days_lead', 14),
            'opportunity_stuck_days_proposal' => AutomationSetting::get('opportunity_stuck.days_proposal', 21),
            'opportunity_stuck_days_negotiation' => AutomationSetting::get('opportunity_stuck.days_negotiation', 30),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        $templateOptions = fn () => WhatsappTemplate::where('status', 'approved')->pluck('name', 'id');

        return $schema
            ->components([
                Form::make([
                    Section::make('Oportunitate câștigată')
                        ->description('Mesaj de mulțumire automat către contact, la marcarea unei oportunități ca „Câștigată".')
                        ->schema([
                            Toggle::make('opportunity_won_enabled')
                                ->label('Activă'),
                            Textarea::make('opportunity_won_message_template')
                                ->label('Mesaj (folosit dacă suntem în fereastra de 24h)')
                                ->rows(3)
                                ->required()
                                ->helperText('Variabile disponibile: [first_name]'),
                            Select::make('opportunity_won_fallback_template_id')
                                ->label('Template aprobat de rezervă (în afara ferestrei de 24h)')
                                ->options($templateOptions)
                                ->searchable()
                                ->helperText(
                                    'Obligatoriu ca mesajul să ajungă și după ce a trecut fereastra de 24h. '.
                                    'Ordinea variabilelor în template-ul Twilio: {{1}} = first_name.'
                                ),
                        ]),
                    Section::make('Oportunitate blocată')
                        ->description('Notificare zilnică către sales_rep-ul responsabil pentru oportunitățile blocate prea mult timp în același status.')
                        ->schema([
                            Toggle::make('opportunity_stuck_enabled')
                                ->label('Activă'),
                            Textarea::make('opportunity_stuck_message_template')
                                ->label('Mesaj (folosit dacă suntem în fereastra de 24h)')
                                ->rows(3)
                                ->required()
                                ->helperText('Variabile disponibile: [user_name], [opp_title], [client_name], [days], [status]'),
                            Select::make('opportunity_stuck_fallback_template_id')
                                ->label('Template aprobat de rezervă (în afara ferestrei de 24h)')
                                ->options($templateOptions)
                                ->searchable()
                                ->helperText(
                                    'Ordinea variabilelor în template-ul Twilio: {{1}}=user_name, {{2}}=opp_title, '.
                                    '{{3}}=client_name, {{4}}=days, {{5}}=status.'
                                ),
                            TextInput::make('opportunity_stuck_send_hour')
                                ->label('Ora trimiterii (0-23)')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(23)
                                ->required()
                                ->helperText('Comanda rulează orar și acționează doar la această oră.'),
                            TextInput::make('opportunity_stuck_days_lead')
                                ->label('Prag zile — status Lead')
                                ->numeric()
                                ->minValue(1)
                                ->required(),
                            TextInput::make('opportunity_stuck_days_proposal')
                                ->label('Prag zile — status Propunere')
                                ->numeric()
                                ->minValue(1)
                                ->required(),
                            TextInput::make('opportunity_stuck_days_negotiation')
                                ->label('Prag zile — status Negociere')
                                ->numeric()
                                ->minValue(1)
                                ->required(),
                        ]),
                ])
                    ->livewireSubmitHandler('save')
                    ->footer([
                        Actions::make([
                            Action::make('save')
                                ->label('Salvează')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        AutomationSetting::set('opportunity_won.enabled', (bool) $data['opportunity_won_enabled']);
        AutomationSetting::set('opportunity_won.message_template', $data['opportunity_won_message_template']);
        AutomationSetting::set('opportunity_won.fallback_template_id', $data['opportunity_won_fallback_template_id']);

        AutomationSetting::set('opportunity_stuck.enabled', (bool) $data['opportunity_stuck_enabled']);
        AutomationSetting::set('opportunity_stuck.message_template', $data['opportunity_stuck_message_template']);
        AutomationSetting::set('opportunity_stuck.fallback_template_id', $data['opportunity_stuck_fallback_template_id']);
        AutomationSetting::set('opportunity_stuck.send_hour', (int) $data['opportunity_stuck_send_hour']);
        AutomationSetting::set('opportunity_stuck.days_lead', (int) $data['opportunity_stuck_days_lead']);
        AutomationSetting::set('opportunity_stuck.days_proposal', (int) $data['opportunity_stuck_days_proposal']);
        AutomationSetting::set('opportunity_stuck.days_negotiation', (int) $data['opportunity_stuck_days_negotiation']);

        Notification::make()
            ->title('Setări salvate')
            ->success()
            ->send();
    }
}
