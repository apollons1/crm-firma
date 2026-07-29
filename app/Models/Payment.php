<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    use HasFactory;

    protected $fillable = [
        'opportunity_id',
        'client_id',
        'contact_id',
        'description',
        'amount',
        'currency',
        'status',
        'stripe_session_id',
        'stripe_payment_intent_id',
        'checkout_url',
        'sent_by_user_id',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
        ];
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

    public function sentByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by_user_id');
    }

    /**
     * super_admin/admin/sales_manager văd toate plățile; sales_rep vede
     * doar plățile legate de oportunitățile pe care le deține.
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
}
