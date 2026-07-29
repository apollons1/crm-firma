<?php

namespace App\Filament\Resources\Opportunities\Actions;

use App\Models\Contact;
use App\Models\Opportunity;
use App\Models\WhatsappMessage;
use App\Models\WhatsappTemplate;
use App\Services\WhatsAppService;
use App\Support\PhoneNumber;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Text;
use Illuminate\Support\Facades\Storage;
use Throwable;
use Twilio\Exceptions\RestException;

class SendWhatsAppAction
{
    /**
     * Limita WhatsApp/Twilio pentru corpul unui mesaj.
     */
    private const MAX_BODY_LENGTH = 1600;

    /**
     * Numărul și codul de activare ale Twilio WhatsApp Sandbox — folosite
     * doar pentru mesajul de avertisment afișat operatorului în formular.
     */
    private const SANDBOX_NUMBER = '+14155238886';

    private const SANDBOX_JOIN_CODE = 'join purple-tiger';

    public static function make(): Action
    {
        return Action::make('sendWhatsApp')
            ->label('Trimite WhatsApp')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->color('success')
            ->visible(fn (Opportunity $record): bool => filled($record->contact?->phone))
            ->schema(fn (Opportunity $record): array => self::buildSchema($record))
            ->modalHeading('Trimite mesaj WhatsApp')
            ->modalSubmitActionLabel('Trimite')
            ->action(fn (array $data, Opportunity $record) => self::send($data, $record));
    }

    /**
     * @return array<int, mixed>
     */
    private static function buildSchema(Opportunity $record): array
    {
        $contact = $record->contact;
        $withinWindow = self::isWithin24HourWindow($contact);

        $fields = [
            Text::make(function () use ($record): string {
                $phone = PhoneNumber::toE164($record->contact->phone);

                return "⚠️ Numărul {$phone} nu a activat încă WhatsApp Sandbox. Pentru a primi mesaje, ".
                    'trimite "'.self::SANDBOX_JOIN_CODE.'" la '.self::SANDBOX_NUMBER.'.';
            })
                ->color('warning')
                ->visible(fn (): bool => ! self::hasJoinedSandbox(PhoneNumber::toE164($record->contact->phone))),
            TextInput::make('to')
                ->label('Către')
                ->default(fn (): string => PhoneNumber::toE164($record->contact->phone))
                ->disabled()
                ->dehydrated()
                ->required(),
        ];

        if ($withinWindow) {
            $fields[] = Textarea::make('body')
                ->label('Mesaj')
                ->default(fn (): string => self::defaultBody($record))
                ->maxLength(self::MAX_BODY_LENGTH)
                ->rows(8)
                ->required();
            $fields[] = FileUpload::make('attachment')
                ->label('Atașament (opțional)')
                ->disk('public')
                ->directory('whatsapp-temp')
                ->acceptedFileTypes(['image/jpeg', 'image/png', 'application/pdf'])
                ->maxSize(5120)
                ->helperText('JPG, PNG, PDF. Maxim 5MB.');

            return $fields;
        }

        // În afara ferestrei de 24h de la ultimul mesaj primit, WhatsApp
        // interzice text liber — doar template-uri pre-aprobate (Content API).
        $fields[] = Text::make(
            '⚠️ Au trecut peste 24h de la ultimul mesaj primit de la acest contact. '.
            'WhatsApp permite în acest caz doar trimiterea unui template aprobat.'
        )->color('warning');
        $fields[] = Select::make('template_id')
            ->label('Template aprobat')
            ->options(fn () => WhatsappTemplate::where('status', 'approved')->pluck('name', 'id'))
            ->required()
            ->live()
            ->searchable()
            ->helperText('Doar template-urile cu status "approved" pot fi folosite.');
        $fields[] = Grid::make(2)
            ->schema(fn (Get $get): array => self::templateVariableFields($get('template_id')))
            ->visible(fn (Get $get): bool => filled($get('template_id')));

        return $fields;
    }

    /**
     * @return array<int, TextInput>
     */
    private static function templateVariableFields(mixed $templateId): array
    {
        $count = WhatsappTemplate::find($templateId)?->variables_count ?? 0;

        if ($count < 1) {
            return [];
        }

        return collect(range(1, $count))
            ->map(fn (int $i) => TextInput::make("variables.{$i}")
                ->label("Variabila {{$i}}")
                ->required())
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function send(array $data, Opportunity $record): void
    {
        $to = $data['to'];
        $withinWindow = self::isWithin24HourWindow($record->contact);

        $status = 'failed';
        $twilioSid = null;
        $errorCode = null;
        $errorMessage = null;
        $mediaUrl = null;
        $mediaType = null;
        $body = null;

        try {
            $whatsapp = app(WhatsAppService::class);

            if ($withinWindow) {
                $body = $data['body'];
                $attachmentPath = $data['attachment'] ?? null;

                if (filled($attachmentPath)) {
                    // Twilio descarcă media dintr-un URL public — nu putem
                    // trimite o cale locală de disc, deci fișierul trebuie
                    // să fie deja accesibil pe storage-ul public.
                    $mediaUrl = Storage::disk('public')->url($attachmentPath);
                    $mediaType = Storage::disk('public')->mimeType($attachmentPath);
                    $twilioSid = $whatsapp->sendMediaMessage($to, $body, $mediaUrl);
                } else {
                    $twilioSid = $whatsapp->sendMessage($to, $body);
                }
            } else {
                $template = WhatsappTemplate::findOrFail($data['template_id']);
                $variables = $data['variables'] ?? [];
                $body = $template->renderBody($variables);
                $twilioSid = $whatsapp->sendTemplate($to, $template->twilio_content_sid, $variables);
            }

            $status = 'sent';
        } catch (RestException $e) {
            $errorCode = (string) $e->getCode();
            $errorMessage = $e->getMessage();
        } catch (Throwable $e) {
            $errorMessage = $e->getMessage();
        }

        WhatsappMessage::create([
            'direction' => 'sent',
            'from_number' => self::fromNumber(),
            'to_number' => $to,
            'body' => $body,
            'media_url' => $mediaUrl,
            'media_type' => $mediaType,
            'twilio_message_sid' => $twilioSid,
            'status' => $status,
            'error_code' => $errorCode,
            'error_message' => $errorMessage,
            'client_id' => $record->client_id,
            'contact_id' => $record->contact_id,
            'opportunity_id' => $record->id,
            'sent_by_user_id' => auth()->id(),
            'sent_at' => now(),
        ]);

        if ($status === 'sent') {
            Notification::make()
                ->title('Mesaj WhatsApp trimis cu succes')
                ->success()
                ->send();
        } else {
            Notification::make()
                ->title('Trimiterea mesajului WhatsApp a eșuat')
                ->body($errorMessage)
                ->danger()
                ->send();
        }
    }

    /**
     * Template implicit al mesajului liber, cu placeholder-ele completate.
     */
    private static function defaultBody(Opportunity $record): string
    {
        $firstName = $record->contact?->first_name ?? '';
        $userName = auth()->user()?->name ?? '';

        return "Bună ziua, {$firstName},\n\n".
            "Vă scriu referitor la oportunitatea \"{$record->title}\". [...]\n\n".
            "Mulțumesc,\n{$userName}";
    }

    /**
     * Numărul WhatsApp al firmei, fără prefixul "whatsapp:" (păstrăm în DB
     * doar numărul E.164, la fel ca to_number/from_number din schema).
     */
    private static function fromNumber(): string
    {
        return str_replace('whatsapp:', '', (string) config('services.twilio.whatsapp_from'));
    }

    /**
     * Twilio nu expune un API care listează participanții unui Sandbox.
     * Aproximăm prin istoricul propriu: dacă am primit vreodată un mesaj
     * de la acest număr, presupunem că a trimis deja "join <cod>".
     */
    private static function hasJoinedSandbox(string $phoneE164): bool
    {
        return WhatsappMessage::received()->where('from_number', $phoneE164)->exists();
    }

    /**
     * Fereastra de 24h (WhatsApp Business): text liber e permis doar dacă
     * ultimul mesaj PRIMIT de la acest contact a fost în ultimele 24h.
     * În afara ei, Twilio respinge mesajele care nu sunt template aprobat.
     */
    private static function isWithin24HourWindow(?Contact $contact): bool
    {
        if (! $contact) {
            return false;
        }

        return WhatsappMessage::isPhoneWithin24HourWindow(PhoneNumber::toE164($contact->phone));
    }
}
