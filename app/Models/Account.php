<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\AccountStatus;
use App\Enums\AccountType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Account extends Model
{
    protected $fillable = [
        'account_no', 'member_id', 'type', 'assigned_collector_id', 'status', 'opened_at', 'closed_at', 'notes', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => AccountType::class,
            'status' => AccountStatus::class,
            'opened_at' => DateOnly::class,
            'closed_at' => DateOnly::class,
        ];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_collector_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function somitiDetail(): HasOne
    {
        return $this->hasOne(SomitiAccountDetail::class);
    }

    public function kistiDetail(): HasOne
    {
        return $this->hasOne(KistiAccountDetail::class);
    }

    public function settlements(): HasMany
    {
        return $this->hasMany(KistiSettlement::class)->latest('id');
    }

    public function activeSettlement(): ?KistiSettlement
    {
        return $this->settlements()->where('status', 'active')->first();
    }

    public function installments(): HasMany
    {
        return $this->hasMany(Installment::class);
    }

    public function shareChanges(): HasMany
    {
        return $this->hasMany(SomitiShareChange::class)->orderBy('effective_date');
    }

    public function isActive(): bool
    {
        return $this->status === AccountStatus::Active;
    }

    public function scopeSomiti(Builder $query): Builder
    {
        return $query->where('type', AccountType::Somiti->value);
    }

    public function scopeKisti(Builder $query): Builder
    {
        return $query->where('type', AccountType::Kisti->value);
    }

    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where('assigned_collector_id', $user->id);
    }
}
