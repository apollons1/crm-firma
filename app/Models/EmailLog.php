<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class EmailLog extends Model
{
    protected $fillable = [
        'google_token_id',
        'sent_by_user_id',
        'opportunity_id',
        'client_id',
        'contact_id',
        'from',
        'to',
        'cc',
        'subject',
        'body',
        'gmail_message_id',
        'direction',
        'status',
        'error_message',
        'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
        ];
    }

    public function gmailAccount(): BelongsTo
    {
        return $this->belongsTo(GoogleToken::class, 'google_token_id');
    }

    public function sentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(EmailAttachment::class);
    }

    /**
     * Client nu are coloană de owner direct, așa că "clienții lui" pentru un
     * sales_rep se derivează prin oportunitățile pe care le deține (user_id).
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->hasAnyRole(['super_admin', 'admin', 'sales_manager'])) {
            return $query;
        }

        $ownedClientIds = Client::whereHas(
            'opportunities',
            fn (Builder $q) => $q->where('user_id', $user->id)
        )->pluck('id');

        return $query->where(function (Builder $q) use ($user, $ownedClientIds) {
            $q->where('sent_by_user_id', $user->id)
                ->orWhereIn('client_id', $ownedClientIds);
        });
    }
}
