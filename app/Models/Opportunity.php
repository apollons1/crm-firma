<?php

namespace App\Models;

use App\Events\OpportunityWon;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Opportunity extends Model
{
    use HasFactory;

    protected $fillable = [
        'client_id',
        'user_id',
        'contact_id',
        'title',
        'description',
        'estimated_value',
        'currency',
        'status',
        'probability',
        'expected_close_date',
        'lead_source',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function emailLogs(): HasMany
    {
        return $this->hasMany(EmailLog::class);
    }

    public function whatsappMessages(): HasMany
    {
        return $this->hasMany(WhatsappMessage::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    protected static function booted(): void
    {
        static::updated(function (Opportunity $opportunity): void {
            if ($opportunity->wasChanged('status') && $opportunity->status === 'won') {
                OpportunityWon::dispatch($opportunity, auth()->user());
            }
        });
    }
}
