<?php

namespace App\Listeners;

use App\Events\OpportunityStuck;
use App\Models\AutomationSetting;
use App\Models\WhatsappTemplate;
use App\Services\WhatsAppAutomationSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendStuckOpportunityNotification implements ShouldQueue
{
    use InteractsWithQueue;

    private const SETTING_PREFIX = 'opportunity_stuck';

    public const DEFAULT_TEMPLATE = "Atenție [user_name]: Oportunitatea '[opp_title]' cu clientul [client_name] ".
        'e blocată de [days] zile în status [status]. Vrei să o reactualizezi?';

    public function __construct(private readonly WhatsAppAutomationSender $sender) {}

    public function handle(OpportunityStuck $event): void
    {
        if (! AutomationSetting::get(self::SETTING_PREFIX.'.enabled', true)) {
            return;
        }

        $opportunity = $event->opportunity;
        $user = $opportunity->user;

        if (! $user || blank($user->phone)) {
            return;
        }

        $template = AutomationSetting::get(self::SETTING_PREFIX.'.message_template', self::DEFAULT_TEMPLATE);
        $fallbackTemplateId = AutomationSetting::get(self::SETTING_PREFIX.'.fallback_template_id');

        $this->sender->send(
            phone: $user->phone,
            freeformTemplate: $template,
            namedVariables: [
                'user_name' => (string) $user->name,
                'opp_title' => (string) $opportunity->title,
                'client_name' => (string) $opportunity->client?->name,
                'days' => (string) $event->daysStuck,
                'status' => self::statusLabel($opportunity->status),
            ],
            fallbackTemplate: $fallbackTemplateId ? WhatsappTemplate::find($fallbackTemplateId) : null,
            logContext: [
                'client_id' => $opportunity->client_id,
                'opportunity_id' => $opportunity->id,
            ],
        );
    }

    private static function statusLabel(string $status): string
    {
        return match ($status) {
            'lead' => 'Lead',
            'qualified' => 'Calificat',
            'proposal' => 'Propunere',
            'negotiation' => 'Negociere',
            default => $status,
        };
    }
}
