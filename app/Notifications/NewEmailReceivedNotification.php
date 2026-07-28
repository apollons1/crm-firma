<?php

namespace App\Notifications;

use App\Filament\Resources\Opportunities\OpportunityResource;
use App\Models\EmailLog;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class NewEmailReceivedNotification extends Notification
{
    use Queueable;

    public function __construct(private readonly EmailLog $emailLog) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $opportunity = $this->emailLog->opportunity;
        $contactName = $this->emailLog->contact?->first_name.' '.$this->emailLog->contact?->last_name;
        $contactName = trim($contactName) ?: $this->emailLog->from;

        return FilamentNotification::make()
            ->title('Email nou primit')
            ->body("Email nou de la {$contactName} referitor la oportunitatea {$opportunity->title}")
            ->icon('heroicon-o-envelope')
            ->actions([
                Action::make('view')
                    ->label('Vezi oportunitatea')
                    ->url(OpportunityResource::getUrl('edit', ['record' => $opportunity]))
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
    }
}
