<?php

namespace App\Observers;

use App\Models\User;
use App\Notifications\PasswordChangedNotification;
use Illuminate\Support\Facades\Auth;

class UserObserver
{
    /**
     * Numărul de parole anterioare păstrate în istoric (folosit și la
     * verificarea „nu poți refolosi una dintre ultimele 5 parole").
     */
    private const HISTORY_LIMIT = 4;

    public function creating(User $user): void
    {
        // blank() (nu doar "necompletat") ca să permită setarea explicită
        // a unei date vechi la creare (ex: seed/migrare conturi existente,
        // teste pentru expirarea parolei) fără să fie suprascrisă aici.
        if (filled($user->password) && blank($user->password_changed_at)) {
            $user->password_changed_at = now();
        }
    }

    public function updating(User $user): void
    {
        if ($user->isDirty('password')) {
            $user->password_changed_at = now();
        }
    }

    /**
     * Rulează DUPĂ salvare cu succes, ca să nu arhivăm/notificăm dacă
     * update-ul eșuează. getOriginal() reflectă încă valorile dinainte de
     * acest save, deci putem prelua parola veche pentru arhivare.
     */
    public function updated(User $user): void
    {
        if (! $user->wasChanged('password')) {
            return;
        }

        $previousHash = $user->getOriginal('password');

        if (filled($previousHash)) {
            $user->passwordHistories()->create(['password_hash' => $previousHash]);
            $this->trimHistory($user);
        }

        $this->notifyPasswordChanged($user);
    }

    private function trimHistory(User $user): void
    {
        $idsToKeep = $user->passwordHistories()->take(self::HISTORY_LIMIT)->pluck('id');

        $user->passwordHistories()->whereNotIn('id', $idsToKeep)->delete();
    }

    private function notifyPasswordChanged(User $user): void
    {
        $changedBy = Auth::user();

        $user->notify(new PasswordChangedNotification(
            changedBySelf: $changedBy?->is($user) ?? false,
            changedByName: $changedBy?->is($user) === false ? $changedBy->name : null,
            ipAddress: request()->ip() ?? 'necunoscută',
            changedAt: now(),
        ));
    }
}
