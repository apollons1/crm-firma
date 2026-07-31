<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class SystemAlert extends Model
{
    protected $fillable = [
        'type',
        'severity',
        'message',
        'metadata',
        'triggered_at',
        'resolved_at',
        'notified_users',
    ];

    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'notified_users' => 'array',
            'triggered_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeResolved(Builder $query): Builder
    {
        return $query->whereNotNull('resolved_at');
    }
}
