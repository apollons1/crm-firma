<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use App\Models\User;

class Opportunity extends Model
{
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
}
