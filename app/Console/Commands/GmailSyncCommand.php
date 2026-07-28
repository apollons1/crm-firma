<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Models\EmailLog;
use App\Models\GoogleToken;
use App\Models\Opportunity;
use App\Notifications\NewEmailReceivedNotification;
use App\Services\GmailMessageParser;
use App\Services\GmailService;
use Google\Service\Gmail\Message;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Throwable;

class GmailSyncCommand extends Command
{
    protected $signature = 'gmail:sync';

    protected $description = 'Sincronizează emailurile primite din conturile Gmail conectate cu jurnalul CRM';

    public function handle(): int
    {
        $accounts = GoogleToken::all();

        if ($accounts->isEmpty()) {
            $this->warn('Niciun cont Gmail conectat — nimic de sincronizat.');

            return self::SUCCESS;
        }

        $totalNew = 0;
        $totalSkipped = 0;
        $totalFailed = 0;

        foreach ($accounts as $account) {
            $this->info("Sincronizez contul {$account->email}...");

            try {
                $gmail = GmailService::forAccount($account);
                $messages = $gmail->listEmails('is:inbox newer_than:1d -from:me', 100);
            } catch (Throwable $e) {
                $this->error("Eroare la conectarea/listarea pentru {$account->email}: {$e->getMessage()}");

                continue;
            }

            foreach ($messages as $stub) {
                $messageId = $stub->getId();

                if (EmailLog::where('gmail_message_id', $messageId)->exists()) {
                    $totalSkipped++;

                    continue;
                }

                try {
                    $this->syncMessage($gmail, $account, $messageId);
                    $totalNew++;
                } catch (Throwable $e) {
                    $totalFailed++;
                    $this->error("Eroare la procesarea mesajului {$messageId}: {$e->getMessage()}");

                    // Scriem un rând "failed" ca să nu reîncercăm la infinit acest
                    // mesaj (dedupe-ul de mai sus e pe gmail_message_id). Trunchiem
                    // mesajul de eroare — poate conține SQL-ul eșuat în întregime,
                    // iar dacă acela conținea un body uriaș, insert-ul de rezervă
                    // ar eșua și el în cascadă și ar opri toată comanda.
                    try {
                        EmailLog::create([
                            'google_token_id' => $account->id,
                            'to' => $account->email,
                            'subject' => '(eroare la sincronizare)',
                            'body' => '',
                            'gmail_message_id' => $messageId,
                            'direction' => 'received',
                            'status' => 'failed',
                            'error_message' => substr($e->getMessage(), 0, 2000),
                        ]);
                    } catch (Throwable $loggingException) {
                        $this->error("Nu am putut salva nici log-ul de eroare pentru {$messageId}: {$loggingException->getMessage()}");
                    }
                }
            }
        }

        $this->info("Sincronizare finalizată: {$totalNew} noi, {$totalSkipped} deja existente, {$totalFailed} eșuate.");

        return self::SUCCESS;
    }

    private function syncMessage(GmailService $gmail, GoogleToken $account, string $messageId): void
    {
        $message = $gmail->getEmail($messageId);

        $fromAddress = GmailMessageParser::fromAddress($message);
        $subject = substr(GmailMessageParser::header($message, 'Subject') ?? '(fără subiect)', 0, 255);
        $sentAt = $this->parseDate(GmailMessageParser::header($message, 'Date'));
        $body = GmailMessageParser::body($message);

        $contact = null;
        $opportunity = null;

        if ($account->auto_associate && $fromAddress) {
            $contact = Contact::where('email', $fromAddress)->first();

            if ($contact) {
                $opportunity = Opportunity::where('contact_id', $contact->id)
                    ->whereNotIn('status', ['won', 'lost'])
                    ->orderByDesc('updated_at')
                    ->first();
            }
        }

        $emailLog = EmailLog::create([
            'google_token_id' => $account->id,
            'opportunity_id' => $opportunity?->id,
            'client_id' => $contact?->client_id,
            'contact_id' => $contact?->id,
            'from' => $fromAddress,
            'to' => $account->email,
            'subject' => $subject,
            'body' => $body,
            'gmail_message_id' => $messageId,
            'direction' => 'received',
            'status' => 'received',
            'sent_at' => $sentAt,
        ]);

        $this->downloadAttachments($gmail, $message, $messageId, $emailLog);

        if ($account->mark_as_read) {
            try {
                $gmail->markAsRead($messageId);
            } catch (Throwable $e) {
                $this->warn("Nu am putut marca mesajul {$messageId} ca citit ({$account->email}): {$e->getMessage()}");
            }
        }

        if ($opportunity?->user) {
            $opportunity->user->notify(new NewEmailReceivedNotification($emailLog));
        }
    }

    private function downloadAttachments(GmailService $gmail, Message $message, string $messageId, EmailLog $emailLog): void
    {
        foreach (GmailMessageParser::attachmentParts($message) as $part) {
            try {
                $contents = $gmail->downloadAttachment($messageId, $part['attachmentId']);
                $path = "email-attachments/incoming/{$emailLog->id}/{$part['filename']}";

                Storage::disk('public')->put($path, $contents);

                $emailLog->attachments()->create([
                    'filename' => $part['filename'],
                    'mime_type' => $part['mimeType'],
                    'size_bytes' => strlen($contents),
                    'storage_path' => $path,
                ]);
            } catch (Throwable $e) {
                $this->warn("Nu am putut descărca atașamentul \"{$part['filename']}\" pentru mesajul {$messageId}: {$e->getMessage()}");
            }
        }
    }

    private function parseDate(?string $dateHeader): Carbon
    {
        if ($dateHeader === null) {
            return now();
        }

        try {
            return Carbon::parse($dateHeader);
        } catch (Throwable) {
            return now();
        }
    }
}
