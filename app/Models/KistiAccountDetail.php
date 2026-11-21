<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\KistiFrequency;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class KistiAccountDetail extends Model
{
    protected $primaryKey = 'account_id';

    public $incrementing = false;

    protected $fillable = [
        'account_id', 'loan_amount', 'service_charge', 'total_payable', 'frequency', 'installment_amount',
        'total_installments', 'last_installment_amount', 'start_date', 'first_installment_date',
    ];

    protected function casts(): array
    {
        return [
            'loan_amount' => 'decimal:2',
            'service_charge' => 'decimal:2',
            'total_payable' => 'decimal:2',
            'installment_amount' => 'decimal:2',
            'last_installment_amount' => 'decimal:2',
            'total_installments' => 'integer',
            'frequency' => KistiFrequency::class,
            'start_date' => DateOnly::class,
            'first_installment_date' => DateOnly::class,
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
