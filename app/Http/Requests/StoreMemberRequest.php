<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\BdMobileRule;
use App\Http\Requests\Concerns\NormalizesBanglaInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Used by both admin and collector. Only the admin picks a collector; a collector is auto-assigned. */
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
    }

    public function rules(): array
    {
        $rules = [
            'name' => ['required', 'string', 'max:150'],
            'business_name' => ['nullable', 'string', 'max:150'],
            'business_address' => ['nullable', 'string', 'max:255'],
            'mobile' => ['required', BdMobileRule::REGEX],
            'joined_at' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];

        if ($this->user()->isAdmin()) {
            $rules['collector_id'] = ['required', 'integer',
                Rule::exists('users', 'id')->where('role', 'collector')->where('is_active', true)];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'mobile.regex' => BdMobileRule::MESSAGE,
            'joined_at.before_or_equal' => 'যোগদানের তারিখ আজকের পরে হতে পারবে না।',
            'collector_id.exists' => 'একজন সক্রিয় কালেক্টর নির্বাচন করুন।',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'সদস্যের নাম',
            'business_name' => 'প্রতিষ্ঠানের নাম',
            'business_address' => 'প্রতিষ্ঠানের ঠিকানা',
            'joined_at' => 'যোগদানের তারিখ',
            'collector_id' => 'দায়িত্বপ্রাপ্ত কালেক্টর',
            'notes' => 'মন্তব্য',
        ];
    }
}
