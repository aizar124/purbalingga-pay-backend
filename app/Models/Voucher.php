<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Voucher extends Model
{
    protected $fillable = [
        'user_id',
        'code',
        'title',
        'description',
        'status',
        'value_amount',
        'redeemed_at',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            'value_amount' => 'integer',
            'redeemed_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
