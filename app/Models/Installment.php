<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\InstallmentStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Installment extends Model
{
    protected $fillable = ['account_id', 'sequence_no', 'due_date', 'amount_due', 'amount_paid', 'amount_waived', 'status'];

    protected function casts(): array
    {
        return [
            'due_date' => DateOnly::class,
            'amount_due' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'amount_waived' => 'decimal:2',
            'status' => InstallmentStatus::class,
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
