<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CollectorMemberAssignment extends Model
{
    protected $fillable = ['member_id', 'collector_id', 'assigned_from', 'assigned_to', 'assigned_by', 'reason'];

    protected function casts(): array
    {
        return ['assigned_from' => 'datetime', 'assigned_to' => 'datetime'];
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function assigner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}
