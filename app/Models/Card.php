<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Card extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'label',
        'status',
        'limit_amount',
        'balance_amount',
        'currency',
        'last_used_at',
    ];

    protected function casts(): array
    {
        return [
            'limit_amount' => 'integer',
            'balance_amount' => 'integer',
            'last_used_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
