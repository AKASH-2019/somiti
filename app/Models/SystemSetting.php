<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $fillable = ['key', 'value', 'type', 'updated_by'];

    public function typedValue(): mixed
    {
        return match ($this->type) {
            'int' => (int) $this->value,
            'decimal' => number_format((float) $this->value, 2, '.', ''),
            'bool' => (bool) $this->value,
            'json' => json_decode((string) $this->value, true) ?? [],
            default => $this->value,
        };
    }
}
