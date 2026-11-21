<?php

namespace App\Models;

use App\Enums\EntryType;
use App\Enums\PaymentMethod;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

class Transaction extends Model
{
    protected $fillable = [
        'receipt_no', 'idempotency_key', 'account_id', 'member_id', 'collector_id', 'entry_type', 'payment_method',
        'amount', 'collected_at', 'status', 'reversed_by', 'reversed_at', 'reversal_reason', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'entry_type' => EntryType::class,
            'payment_method' => PaymentMethod::class,
            'status' => TransactionStatus::class,
            'amount' => 'decimal:2',
            'collected_at' => 'datetime',
            'reversed_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Transactions are never deleted — reverse them instead.'));
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function reverser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reversed_by');
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(TransactionAllocation::class)->orderBy('covered_date')->orderBy('id');
    }

    public function isReversed(): bool
    {
        return $this->status === TransactionStatus::Reversed;
    }

    /** Admin: all. Collector: what they received, plus anything on accounts now assigned to them. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where(fn ($q) => $q
            ->where('collector_id', $user->id)
            ->orWhereHas('account', fn ($a) => $a->where('assigned_collector_id', $user->id)));
    }
}
