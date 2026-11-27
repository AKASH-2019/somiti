<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KistiSettlement extends Model
{
    protected $fillable = [
        'account_id', 'transaction_id', 'outstanding_before', 'settled_amount', 'waived_amount', 'reason',
        'receiver_id', 'settled_by', 'status', 'reversed_by', 'reversed_at',
    ];

    protected function casts(): array
    {
        return [
            'outstanding_before' => 'decimal:2',
            'settled_amount' => 'decimal:2',
            'waived_amount' => 'decimal:2',
            'reversed_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    public function settler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'settled_by');
    }
}
