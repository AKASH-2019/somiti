<?php

namespace App\Http\Requests;

use App\Enums\KistiFrequency;
use App\Http\Requests\Concerns\NormalizesBanglaInput;
use App\Models\Account;
use App\Models\Member;
use App\Services\Kisti\KistiAccountService;
use DomainException;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreKistiAccountRequest extends FormRequest
{
    use NormalizesBanglaInput;

    public function authorize(): bool
    {
        return true; // member-level check in after()
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeNumbers(['loan_amount', 'service_charge', 'installment_amount']);
    }

    public function rules(): array
    {
        $money = ['numeric', 'decimal:0,2', 'max:99999999'];

        return [
            'member_id' => ['required', 'integer', 'exists:members,id'],
            'frequency' => ['required', Rule::enum(KistiFrequency::class)],
            'loan_amount' => ['required', ...$money, 'gt:0'],
            'service_charge' => ['nullable', ...$money, 'min:0'],
            'installment_amount' => ['required', ...$money, 'gt:0'],
            'start_date' => ['required', 'date', 'before_or_equal:'.today()->addDays(30)->toDateString()],
            'first_installment_date' => ['required', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator) {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $member = Member::query()->find($this->input('member_id'));
            if (! $member || ! $this->user()->can('create', [Account::class, $member])) {
                $validator->errors()->add('member_id', 'এই সদস্যকে ঋণ দেওয়া যাবে না (সদস্য নিষ্ক্রিয় অথবা আপনার দায়িত্বে নেই)।');

                return;
            }
            if ($member->joined_at && $this->date('start_date')->lt($member->joined_at)) {
                $validator->errors()->add('start_date', 'কিস্তি শুরুর তারিখ সদস্যের যোগদানের তারিখের আগে হতে পারবে না।');
            }
            try {
                $terms = KistiAccountService::terms((string) $this->input('loan_amount'),
                    (string) ($this->input('service_charge') ?: '0'), (string) $this->input('installment_amount'));
                if ($terms['count'] > KistiAccountService::MAX_INSTALLMENTS) {
                    $validator->errors()->add('installment_amount', 'কিস্তির সংখ্যা '.bn_digits(KistiAccountService::MAX_INSTALLMENTS).'টির বেশি হয়ে যাচ্ছে। কিস্তির টাকা বাড়ান।');
                }
            } catch (DomainException $e) {
                $validator->errors()->add('installment_amount', $e->getMessage());
            }
        }];
    }

    public function attributes(): array
    {
        return [
            'member_id' => 'সদস্য', 'frequency' => 'কিস্তির ধরন', 'loan_amount' => 'ঋণের পরিমাণ',
            'service_charge' => 'সার্ভিস চার্জ', 'installment_amount' => 'প্রতি কিস্তির টাকা',
            'start_date' => 'কিস্তি শুরুর তারিখ', 'first_installment_date' => 'প্রথম কিস্তির তারিখ',
        ];
    }

    public function messages(): array
    {
        return [
            'first_installment_date.after_or_equal' => 'প্রথম কিস্তির তারিখ কিস্তি শুরুর তারিখের আগে হতে পারবে না।',
            'start_date.before_or_equal' => 'কিস্তি শুরুর তারিখ আজ থেকে ৩০ দিনের মধ্যে হতে হবে।',
        ];
    }
}
