<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\AllocationTiming;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TransactionAllocation extends Model
{
    protected $fillable = ['transaction_id', 'installment_id', 'covered_date', 'amount', 'timing', 'status'];

    protected function casts(): array
    {
        return [
            'covered_date' => DateOnly::class,
            'amount' => 'decimal:2',
            'timing' => AllocationTiming::class,
        ];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class);
    }

    public function installment(): BelongsTo
    {
        return $this->belongsTo(Installment::class);
    }
}
