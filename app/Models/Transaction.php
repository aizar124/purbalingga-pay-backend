<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Transaction extends Model
{
    protected $fillable = [
        'user_id',
        'reference',
        'type',
        'title',
        'description',
        'amount',
        'status',
        'happened_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'happened_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
