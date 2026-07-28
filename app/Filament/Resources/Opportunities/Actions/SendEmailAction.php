<?php

namespace App\Filament\Resources\Opportunities\Actions;

use App\Models\EmailLog;
use App\Models\GoogleToken;
use App\Models\Opportunity;
use App\Services\GmailService;
use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Throwable;

class SendEmailAction
{
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
            ])
            ->modalHeading('Trimite email')
            ->modalSubmitActionLabel('Trimite')
            ->action(function (array $data, Opportunity $record): void {
                $googleToken = GoogleToken::first();

                $status = 'failed';
                $gmailMessageId = null;
                $errorMessage = null;

                try {
                    $message = GmailService::forCompanyAccount()->sendEmail(
                        to: $data['to'],
                        subject: $data['subject'],
                        body: $data['body'],
                    );

                    $gmailMessageId = $message->getId();
                    $status = 'sent';
                } catch (Throwable $e) {
                    $errorMessage = $e->getMessage();
                }

                EmailLog::create([
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
