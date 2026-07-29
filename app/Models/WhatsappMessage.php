<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class WhatsappMessage extends Model
{
    protected $fillable = [
        'direction',
        'from_number',
        'to_number',
        'body',
        'media_url',
        'media_type',
        'twilio_message_sid',
        'status',
        'error_code',
        'error_message',
        'client_id',
        'contact_id',
        'opportunity_id',
        'sent_by_user_id',
        'sent_at',
        'delivered_at',
        'read_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
            'read_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function scopeReceived(Builder $query): Builder
    {
        return $query->where('direction', 'received');
    }

    public function scopeSent(Builder $query): Builder
    {
        return $query->where('direction', 'sent');
    }

    /**
     * super_admin/admin/sales_manager văd toate mesajele; sales_rep vede
     * doar mesajele legate de oportunitățile pe care le deține.
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['super_admin', 'admin', 'sales_manager'])) {
            return $query;
        }

        return $query->whereHas(
            'opportunity',
            fn (Builder $q) => $q->where('user_id', $user->id)
        );
    }

    /**
     * Fereastra de 24h (WhatsApp Business): text liber e permis doar dacă
     * numărul dat ne-a trimis un mesaj în ultimele 24h. Cheia e telefonul,
     * nu un Contact — se aplică identic pentru clienți și pentru useri interni
     * (ex: notificarea sales_rep-ului la o oportunitate blocată).
     */
    public static function isPhoneWithin24HourWindow(string $e164Phone): bool
    {
        $lastReceivedAt = static::where('from_number', $e164Phone)
            ->where('direction', 'received')
            ->max('sent_at');

        if ($lastReceivedAt === null) {
            return false;
        }

        return abs(now()->diffInHours(Carbon::parse($lastReceivedAt))) < 24;
    }
}
