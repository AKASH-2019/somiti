<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SomitiAccountDetail extends Model
{
    protected $primaryKey = 'account_id';

    public $incrementing = false;

    protected $fillable = [
        'account_id', 'number_of_shares', 'share_value', 'daily_deposit_amount', 'start_date',
        'opening_balance', 'opening_balance_note', 'opening_balance_by',
    ];

    protected function casts(): array
    {
        return [
            'number_of_shares' => 'integer',
            'share_value' => 'decimal:2',
            'daily_deposit_amount' => 'decimal:2',
            'start_date' => DateOnly::class,
            'opening_balance' => 'decimal:2',
        ];
    }

    public function openingBalanceUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'opening_balance_by');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
