<?php

namespace App\Models;

use App\Enums\HandoverStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

class CashHandover extends Model
{
    protected $fillable = [
        'handover_number', 'sender_collector_id', 'receiver_collector_id', 'amount', 'handover_datetime',
        'received_datetime', 'status', 'notes', 'rejection_reason', 'confirmed_by', 'cancelled_at',
        'disputed_by', 'disputed_at', 'dispute_reason', 'resolved_by', 'resolved_at', 'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'status' => HandoverStatus::class,
            'amount' => 'decimal:2',
            'handover_datetime' => 'datetime',
            'received_datetime' => 'datetime',
            'cancelled_at' => 'datetime',
            'disputed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(fn () => throw new LogicException('Handovers are never deleted.'));
    }

    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_collector_id');
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_collector_id');
    }

    public function confirmer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function disputer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'disputed_by');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function scopeInvolving(Builder $query, User $user): Builder
    {
        return $query->where(fn ($q) => $q->where('sender_collector_id', $user->id)->orWhere('receiver_collector_id', $user->id));
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->involving($user);
    }
}
