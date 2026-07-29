<?php

namespace App\Filament\Resources\Opportunities\Actions;

use App\Models\Opportunity;
use App\Models\Payment;
use App\Services\StripeService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Actions\Action as NotificationAction;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class SendPaymentLinkAction
{
    public static function make(): Action
    {
        return Action::make('sendPaymentLink')
            ->label('Trimite link de plată')
            ->icon('heroicon-o-credit-card')
            ->color('warning')
            ->schema(fn (Opportunity $record): array => [
                TextInput::make('amount')
                    ->label('Sumă')
                    ->numeric()
                    ->minValue(0.01)
                    ->default($record->estimated_value)
                    ->required(),
                Select::make('currency')
                    ->label('Monedă')
                    ->options(['RON' => 'RON', 'EUR' => 'EUR', 'USD' => 'USD'])
                    ->default($record->currency ?? 'RON')
                    ->required(),
            ])
            ->modalHeading('Trimite link de plată')
            ->modalSubmitActionLabel('Generează link')
            ->action(fn (array $data, Opportunity $record) => self::send($data, $record));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function send(array $data, Opportunity $record): void
    {
        $amount = (float) $data['amount'];
        $currency = $data['currency'];

        try {
            $session = app(StripeService::class)->createCheckoutSessionForOpportunity(
                opportunity: $record,
                amount: $amount,
                currency: $currency,
            );
        } catch (Throwable $e) {
            Log::error('Eroare la generarea linkului de plată Stripe.', [
                'opportunity_id' => $record->id,
                'exception' => $e->getMessage(),
            ]);

            Notification::make()
                ->title('Generarea linkului de plată a eșuat')
                ->body($e->getMessage())
                ->danger()
                ->send();

            return;
        }

        Payment::create([
            'opportunity_id' => $record->id,
            'client_id' => $record->client_id,
            'contact_id' => $record->contact_id,
            'description' => $record->title,
            'amount' => $amount,
            'currency' => $currency,
            'status' => 'pending',
            'stripe_session_id' => $session->id,
            'checkout_url' => $session->url,
            'sent_by_user_id' => auth()->id(),
        ]);

        Notification::make()
            ->title('Link de plată generat cu succes')
            ->body($session->url)
            ->success()
            ->persistent()
            ->actions([
                NotificationAction::make('open')
                    ->label('Deschide linkul')
                    ->url($session->url)
                    ->openUrlInNewTab(),
            ])
            ->send();
    }
}
