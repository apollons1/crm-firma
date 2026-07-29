<?php

namespace App\Services;

use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Support\PhoneNumber;
use RuntimeException;
use Throwable;
use Twilio\Exceptions\RestException;

/**
 * Trimite mesaje WhatsApp declanșate automat de evenimente CRM (nu de un
 * operator), respectând regula de 24h: text liber în fereastră, template
 * pre-aprobat obligatoriu în afara ei. Folosit de listener-ele automatizărilor
 * (SendWonNotification, SendStuckOpportunityNotification) — nu de modalul
 * interactiv SendWhatsAppAction, unde operatorul alege el template-ul.
 */
class WhatsAppAutomationSender
{
    public function __construct(private readonly WhatsAppService $whatsapp) {}

    /**
     * @param  array<string, string>  $namedVariables  ex: ['first_name' => 'Ion'] — înlocuiește [first_name] din $freeformTemplate
     * @param  array<string, mixed>  $logContext  câmpuri suplimentare pentru WhatsappMessage (opportunity_id, client_id, contact_id, sent_by_user_id)
     */
    public function send(
        string $phone,
        string $freeformTemplate,
        array $namedVariables,
        ?WhatsappTemplate $fallbackTemplate,
        array $logContext = [],
    ): WhatsappMessage {
        $to = PhoneNumber::toE164($phone);
        $withinWindow = WhatsappMessage::isPhoneWithin24HourWindow($to);

        $status = 'failed';
        $twilioSid = null;
        $errorCode = null;
        $errorMessage = null;
        $body = null;

        try {
            if ($withinWindow) {
                $body = self::interpolateNamed($freeformTemplate, $namedVariables);
                $twilioSid = $this->whatsapp->sendMessage($to, $body);
            } elseif ($fallbackTemplate?->status === 'approved') {
                $positionalVariables = self::toPositional($namedVariables);
                $body = $fallbackTemplate->renderBody($positionalVariables);
                $twilioSid = $this->whatsapp->sendTemplate($to, $fallbackTemplate->twilio_content_sid, $positionalVariables);
            } else {
                throw new RuntimeException(
                    'În afara ferestrei de 24h și niciun template aprobat configurat pentru această automatizare — mesajul nu a fost trimis.'
                );
            }

            $status = 'sent';
        } catch (RestException $e) {
            $errorCode = (string) $e->getCode();
            $errorMessage = $e->getMessage();
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
        }

        return WhatsappMessage::create(array_merge([
            'direction' => 'sent',
            'from_number' => str_replace('whatsapp:', '', (string) config('services.twilio.whatsapp_from')),
            'to_number' => $to,
            'body' => $body,
            'twilio_message_sid' => $twilioSid,
            'status' => $status,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'sent_at' => now(),
        ], $logContext));
    }

    /**
     * @param  array<string, string>  $namedVariables
     */
    private static function interpolateNamed(string $template, array $namedVariables): string
    {
        $search = array_map(fn (string $key): string => "[{$key}]", array_keys($namedVariables));

        return str_replace($search, array_values($namedVariables), $template);
    }

    /**
     * Ordinea cheilor din $namedVariables devine ordinea {{1}}, {{2}}, ... din
     * template-ul Twilio de fallback — documentat pe pagina de setări, ca
     * adminul să aprobe template-ul cu variabilele în ordinea corectă.
     *
     * @param  array<string, string>  $namedVariables
     * @return array<int, string>
     */
    private static function toPositional(array $namedVariables): array
    {
        return array_combine(
            range(1, count($namedVariables)),
            array_values($namedVariables)
        );
    }
}
