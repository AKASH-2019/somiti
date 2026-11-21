<?php

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

class SettingService
{
    private const CACHE_KEY = 'system_settings.all';

    public const DEFAULTS = [
        'org_name' => ['value' => 'আমাদের সমিতি', 'type' => 'string'],
        'org_address' => ['value' => '', 'type' => 'string'],
        'org_mobile' => ['value' => '', 'type' => 'string'],
        'share_value' => ['value' => '20.00', 'type' => 'decimal'],
        'weekly_off_days' => ['value' => '[]', 'type' => 'json'],
        'receipt_footer' => ['value' => 'আমাদের সাথে থাকার জন্য ধন্যবাদ।', 'type' => 'string'],
    ];

    public function all(): array
    {
        return Cache::rememberForever(self::CACHE_KEY, function () {
            if (! Schema::hasTable('system_settings')) {
                return [];
            }

            return SystemSetting::query()->get()
                ->mapWithKeys(fn (SystemSetting $s) => [$s->key => $s->typedValue()])
                ->all();
        });
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();
        if (array_key_exists($key, $all)) {
            return $all[$key];
        }
        if ($default === null && isset(self::DEFAULTS[$key])) {
            $d = self::DEFAULTS[$key];

            return (new SystemSetting(['value' => $d['value'], 'type' => $d['type']]))->typedValue();
        }

        return $default;
    }

    public function set(string $key, mixed $value, ?int $userId = null): void
    {
        $type = self::DEFAULTS[$key]['type'] ?? 'string';
        $stored = match ($type) {
            'json' => json_encode(array_values((array) $value)),
            'decimal' => number_format((float) $value, 2, '.', ''),
            'bool' => $value ? '1' : '0',
            default => (string) $value,
        };

        SystemSetting::query()->updateOrCreate(
            ['key' => $key],
            ['value' => $stored, 'type' => $type, 'updated_by' => $userId],
        );

        $this->flush();
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** Current share value as a decimal string, e.g. "20.00". New Somiti accounts snapshot this. */
    public function shareValue(): string
    {
        return (string) $this->get('share_value');
    }

    /** @return int[] 0 = Sunday … 6 = Saturday */
    public function weeklyOffDays(): array
    {
        return array_map('intval', (array) $this->get('weekly_off_days'));
    }
}
