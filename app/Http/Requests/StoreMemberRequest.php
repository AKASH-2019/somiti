<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\BdMobileRule;
use App\Http\Requests\Concerns\NormalizesBanglaInput;
use App\Models\User;
use App\Services\Members\MemberCodeService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * New member, used by admin and collector. Only the admin picks a collector; a collector is auto-assigned.
 * Optionally opens the member's Somiti account in the same save (open_somiti = 1).
 */
class StoreMemberRequest extends FormRequest
{
    use NormalizesBanglaInput;

    public function authorize(): bool
    {
        return $this->user()?->can('create', \App\Models\Member::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhones(['mobile']);
        $this->normalizeNumbers(['number_of_shares', 'opening_balance']);
        $this->merge(['member_code' => MemberCodeService::normalize($this->input('member_code'))]);
    }

    /** Member fields — shared with the Kisti form's "new member" option. */
    public static function memberRules(User $user, ?int $ignoreMemberId = null): array
    {
        $rules = [
            'member_code' => ['nullable', 'string', 'max:20', 'regex:/^[A-Z0-9]+(-[A-Z0-9]+)*$/',
                Rule::unique('members', 'member_code')->ignore($ignoreMemberId)],
            'name' => ['required', 'string', 'max:150'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'business_address' => ['nullable', 'string', 'max:255'],
            'mobile' => ['required', BdMobileRule::REGEX],
            'joined_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
        if ($user->isAdmin() && $ignoreMemberId === null) {
            $rules['collector_id'] = ['required', 'integer',
                Rule::exists('users', 'id')->where('role', 'collector')->where('is_active', true)];
        }

        return $rules;
    }

    public static function memberMessages(): array
    {
        return [
            'mobile.regex' => BdMobileRule::MESSAGE,
            'joined_at.before_or_equal' => 'যোগদানের তারিখ আজকের পরে হতে পারবে না।',
            'collector_id.exists' => 'একজন সক্রিয় কালেক্টর নির্বাচন করুন।',
            'member_code.unique' => 'এই সদস্য আইডি আগেই ব্যবহৃত হয়েছে। অন্য একটি আইডি দিন।',
            'member_code.regex' => 'সদস্য আইডিতে ইংরেজি অক্ষর, সংখ্যা ও হাইফেন (-) ব্যবহার করুন, যেমন: S5-010।',
        ];
    }

    public static function memberAttributes(): array
    {
        return [
            'member_code' => 'সদস্য আইডি',
            'name' => 'সদস্যের নাম',
            'business_name' => 'প্রতিষ্ঠানের নাম',
            'business_address' => 'প্রতিষ্ঠানের ঠিকানা',
            'joined_at' => 'যোগদানের তারিখ',
            'collector_id' => 'দায়িত্বপ্রাপ্ত কালেক্টর',
            'notes' => 'মন্তব্য',
        ];
    }

    public function rules(): array
    {
        return self::memberRules($this->user()) + [
            'open_somiti' => ['nullable', 'boolean'],
            'number_of_shares' => ['exclude_unless:open_somiti,1', 'required', 'integer', 'min:1', 'max:1000'],
            'somiti_start_date' => ['exclude_unless:open_somiti,1', 'required', 'date', 'after_or_equal:joined_at',
                'before_or_equal:'.today()->addDays(30)->toDateString()],
            'opening_balance' => ['exclude_unless:open_somiti,1', 'nullable', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'],
            'opening_balance_note' => ['exclude_unless:open_somiti,1', 'nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return self::memberMessages() + [
            'somiti_start_date.after_or_equal' => 'সঞ্চয় শুরুর তারিখ যোগদানের তারিখের আগে হতে পারবে না।',
            'somiti_start_date.before_or_equal' => 'সঞ্চয় শুরুর তারিখ আজ থেকে ৩০ দিনের মধ্যে হতে হবে।',
        ];
    }

    public function attributes(): array
    {
        return self::memberAttributes();
    }
}
