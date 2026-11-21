<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'username', 'mobile', 'email', 'role', 'is_active', 'opening_cash', 'password',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'opening_cash' => 'decimal:2',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    public function isCollector(): bool
    {
        return $this->role === UserRole::Collector;
    }

    /** Members currently assigned to this collector. */
    public function members(): HasMany
    {
        return $this->hasMany(Member::class, 'collector_id');
    }

    public function scopeCollectors(Builder $query): Builder
    {
        return $query->where('role', UserRole::Collector->value);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Admins and collectors can both hold cash, collect and receive handovers. */
    public function scopeCashHolders(Builder $query): Builder
    {
        return $query->whereIn('role', [UserRole::Admin->value, UserRole::Collector->value]);
    }
}
