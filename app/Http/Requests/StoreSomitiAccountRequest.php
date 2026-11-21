<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\NormalizesBanglaInput;
use App\Models\Member;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreSomitiAccountRequest extends FormRequest
{
    use NormalizesBanglaInput;

    public function authorize(): bool
    {
        return true; // member-level check happens in after(), so a bad member_id shows a field error, not a 403
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeNumbers(['number_of_shares', 'opening_balance']);
    }

    public function rules(): array
    {
        return [
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'number_of_shares' => ['required', 'integer', 'min:1', 'max:1000'],
            'start_date' => ['required', 'date', 'before_or_equal:'.today()->addDays(30)->toDateString()],
            'notes' => ['nullable', 'string', 'max:500'],
            'opening_balance' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'],
            'opening_balance_note' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $member = Member::query()->find($this->input('member_id'));
            if (! $member || ! $this->user()->can('create', [\App\Models\Account::class, $member])) {
                $validator->errors()->add('member_id', 'এই সদস্যের জন্য অ্যাকাউন্ট খোলা যাবে না (সদস্য নিষ্ক্রিয় অথবা আপনার দায়িত্বে নেই)।');

                return;
            }
            if ($member->joined_at && $this->date('start_date')->lt($member->joined_at)) {
                $validator->errors()->add('start_date', 'সঞ্চয় শুরুর তারিখ সদস্যের যোগদানের তারিখের আগে হতে পারবে না।');
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'member_id' => 'সদস্য', 'number_of_shares' => 'শেয়ারের সংখ্যা', 'start_date' => 'সঞ্চয় শুরুর তারিখ',
            'opening_balance' => 'প্রারম্ভিক সঞ্চয়', 'opening_balance_note' => 'প্রারম্ভিক সঞ্চয়ের বিবরণ',
        ];
    }

    public function messages(): array
    {
        return ['start_date.before_or_equal' => 'সঞ্চয় শুরুর তারিখ আজ থেকে ৩০ দিনের মধ্যে হতে হবে।'];
    }
}
