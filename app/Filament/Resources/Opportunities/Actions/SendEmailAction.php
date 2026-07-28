<?php

namespace App\Filament\Resources\Opportunities\Actions;

use App\Models\EmailLog;
use App\Models\GoogleToken;
use App\Models\Opportunity;
use App\Services\GmailService;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Storage;
use Throwable;

class SendEmailAction
{
    /**
     * Limita Gmail pentru dimensiunea totală a unui mesaj cu atașamente.
     */
    private const MAX_ATTACHMENTS_BYTES = 25 * 1024 * 1024;

    public static function make(): Action
    {
        return Action::make('sendEmail')
            ->label('Trimite email')
            ->icon('heroicon-o-envelope')
            ->color('primary')
            ->visible(fn (Opportunity $record): bool => filled($record->contact?->email))
            ->schema(fn (Opportunity $record): array => [
                TextInput::make('to')
                    ->label('Către')
                    ->default($record->contact?->email)
                    ->disabled()
                    ->dehydrated()
                    ->required(),
                TextInput::make('cc')
                    ->label('CC')
                    ->email()
                    ->maxLength(255),
                TextInput::make('subject')
                    ->label('Subiect')
                    ->default("Re: {$record->title}")
                    ->required()
                    ->maxLength(255),
                RichEditor::make('body')
                    ->label('Mesaj')
                    ->default(
                        '<p>Bună ziua,</p><p></p><p>&nbsp;</p><p>Cu stimă,</p>'
                    )
                    ->required(),
                Hidden::make('upload_batch')
                    ->default(fn (): string => (string) now()->timestamp),
                FileUpload::make('attachments')
                    ->label('Atașamente')
                    ->multiple()
                    ->disk('public')
                    ->directory(fn (Get $get): string => 'email-attachments/'.auth()->id().'/'.$get('upload_batch'))
                    ->storeFileNamesIn('attachment_names')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'image/jpeg',
                        'image/png',
                        'application/zip',
                    ])
                    ->maxSize(self::MAX_ATTACHMENTS_BYTES / 1024)
                    ->helperText('PDF, DOCX, XLSX, JPG, PNG, ZIP. Maxim 25MB în total (limita Gmail).'),
            ])
            ->modalHeading('Trimite email')
            ->modalSubmitActionLabel('Trimite')
            ->action(function (array $data, Opportunity $record): void {
                $attachmentPaths = $data['attachments'] ?? [];
                $attachmentNames = $data['attachment_names'] ?? [];

                $totalBytes = collect($attachmentPaths)
                    ->sum(fn (string $path): int => Storage::disk('public')->size($path));

                if ($totalBytes > self::MAX_ATTACHMENTS_BYTES) {
                    Notification::make()
                        ->title('Atașamentele depășesc 25MB în total')
                        ->body('Gmail nu acceptă mesaje mai mari de 25MB. Elimină sau micșorează atașamentele și încearcă din nou.')
                        ->danger()
                        ->send();

                    return;
                }

                $googleToken = GoogleToken::first();

                $status = 'failed';
                $gmailMessageId = null;
                $errorMessage = null;

                try {
                    $message = GmailService::forCompanyAccount()->sendEmail(
                        to: $data['to'],
                        subject: $data['subject'],
                        body: $data['body'],
                        cc: $data['cc'] ?: null,
                        attachments: $attachmentPaths,
                    );

                    $gmailMessageId = $message->getId();
                    $status = 'sent';
                } catch (Throwable $e) {
                    $errorMessage = $e->getMessage();
                }

                $emailLog = EmailLog::create([
                    'google_token_id' => $googleToken?->id,
                    'sent_by_user_id' => auth()->id(),
                    'opportunity_id' => $record->id,
                    'client_id' => $record->client_id,
                    'contact_id' => $record->contact_id,
                    'to' => $data['to'],
                    'cc' => $data['cc'] ?: null,
                    'subject' => $data['subject'],
                    'body' => $data['body'],
                    'gmail_message_id' => $gmailMessageId,
                    'direction' => 'sent',
                    'status' => $status,
                    'error_message' => $errorMessage,
                    'sent_at' => $status === 'sent' ? now() : null,
                ]);

                foreach ($attachmentPaths as $key => $path) {
                    $emailLog->attachments()->create([
                        'filename' => $attachmentNames[$key] ?? basename($path),
                        'mime_type' => Storage::disk('public')->mimeType($path) ?: 'application/octet-stream',
                        'size_bytes' => Storage::disk('public')->size($path),
                        'storage_path' => $path,
                    ]);
                }

                if ($status === 'sent') {
                    Notification::make()
                        ->title('Email trimis cu succes')
                        ->success()
                        ->send();
                } else {
                    Notification::make()
                        ->title('Trimiterea email-ului a eșuat')
                        ->body($errorMessage)
                        ->danger()
                        ->send();
                }
            });
    }
}
