<?php

namespace App\Listeners;

use App\Events\OpportunityWon;
use App\Models\AutomationSetting;
use App\Models\WhatsappTemplate;
use App\Services\WhatsAppAutomationSender;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

class SendWonNotification implements ShouldQueue
{
    use InteractsWithQueue;

    private const SETTING_PREFIX = 'opportunity_won';

    public const DEFAULT_TEMPLATE = 'Bună ziua [first_name]! Mulțumim pentru încrederea acordată. '.
        'În următoarele 24h vă trimitem documentele finale. O zi excelentă!';

    public function __construct(private readonly WhatsAppAutomationSender $sender) {}

    public function handle(OpportunityWon $event): void
    {
        if (! AutomationSetting::get(self::SETTING_PREFIX.'.enabled', true)) {
            return;
        }

        $contact = $event->opportunity->contact;

        if (! $contact || blank($contact->phone)) {
            return;
        }

        $template = AutomationSetting::get(self::SETTING_PREFIX.'.message_template', self::DEFAULT_TEMPLATE);
        $fallbackTemplateId = AutomationSetting::get(self::SETTING_PREFIX.'.fallback_template_id');

        $this->sender->send(
            phone: $contact->phone,
            freeformTemplate: $template,
            namedVariables: ['first_name' => (string) $contact->first_name],
            fallbackTemplate: $fallbackTemplateId ? WhatsappTemplate::find($fallbackTemplateId) : null,
            logContext: [
                'client_id' => $contact->client_id,
                'contact_id' => $contact->id,
                'opportunity_id' => $event->opportunity->id,
                'sent_by_user_id' => $event->markedBy?->id,
            ],
        );
    }
}
