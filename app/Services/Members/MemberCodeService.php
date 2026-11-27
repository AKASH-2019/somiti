<?php

namespace App\Services\Members;

use App\Models\Member;
use App\Services\SettingService;
use App\Support\BanglaFormatter;

/**
 * Member IDs look like S5-001, S5-002 … The prefix comes from settings.
 * The next number is suggested, but staff may type another free one (e.g. a paper-register number like S5-010).
 */
class MemberCodeService
{
    public function __construct(private SettingService $settings) {}

    public function prefix(): string
    {
        return strtoupper((string) $this->settings->get('member_id_prefix', 'S5')) ?: 'S5';
    }

    /** Highest number used with the current prefix + 1, at least 3 digits: S5-001. */
    public function next(): string
    {
        $prefix = $this->prefix();
        $max = Member::query()->where('member_code', 'like', $prefix.'-%')->pluck('member_code')
            ->map(fn ($code) => (int) preg_replace('/\D/', '', substr($code, strlen($prefix) + 1)))
            ->max() ?? 0;

        return $prefix.'-'.str_pad((string) ($max + 1), 3, '0', STR_PAD_LEFT);
    }

    /** "s5 - ১০" → "S5-10" ; empty → null */
    public static function normalize(?string $code): ?string
    {
        $code = strtoupper(preg_replace('/\s+/', '', BanglaFormatter::toAsciiDigits((string) $code)));

        return $code === '' ? null : $code;
    }
}
