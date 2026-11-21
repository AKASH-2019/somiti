<?php

namespace App\Models;

use App\Casts\DateOnly;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SomitiShareChange extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'account_id', 'old_shares', 'new_shares', 'share_value', 'old_daily_amount',
        'new_daily_amount', 'effective_date', 'changed_by', 'reason',
    ];

    protected function casts(): array
    {
        return [
            'effective_date' => DateOnly::class,
            'share_value' => 'decimal:2',
            'old_daily_amount' => 'decimal:2',
            'new_daily_amount' => 'decimal:2',
        ];
    }

    public function changer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
