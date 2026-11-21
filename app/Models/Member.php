<?php

namespace App\Models;

use App\Casts\DateOnly;
use App\Enums\MemberStatus;
use App\Support\BanglaFormatter;
use Database\Factories\MemberFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    /** @use HasFactory<MemberFactory> */
    use HasFactory;

    protected $fillable = [
        'member_code', 'name', 'business_name', 'business_address', 'mobile',
        'joined_at', 'status', 'collector_id', 'notes', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'joined_at' => DateOnly::class,
            'status' => MemberStatus::class,
        ];
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(CollectorMemberAssignment::class)->latest('assigned_from')->latest('id');
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(Account::class);
    }

    public function isActive(): bool
    {
        return $this->status === MemberStatus::Active;
    }

    /** Admin sees everyone; a collector sees only members currently assigned to them. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->isAdmin() ? $query : $query->where('collector_id', $user->id);
    }

    /** Matches ID, name, business name or mobile. Bengali digits in the search box are accepted. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        $term = trim(BanglaFormatter::toAsciiDigits($term));
        if ($term === '') {
            return $query;
        }
        $like = '%'.addcslashes($term, '%_\\').'%';

        return $query->where(fn (Builder $q) => $q
            ->where('member_code', 'like', $like)
            ->orWhere('name', 'like', $like)
            ->orWhere('business_name', 'like', $like)
            ->orWhere('mobile', 'like', $like));
    }
}
