<?php

namespace App\Models;

use App\Enums\LedgerType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CashLedgerEntry extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'collector_cash_ledger';

    protected $fillable = [
        'collector_id', 'transaction_type', 'reference_type', 'reference_id', 'debit', 'credit',
        'transaction_datetime', 'description', 'entry_group', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'transaction_type' => LedgerType::class,
            'debit' => 'decimal:2',
            'credit' => 'decimal:2',
            'transaction_datetime' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('The cash ledger is append-only.'));
        static::deleting(fn () => throw new LogicException('The cash ledger is append-only.'));
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }
}
