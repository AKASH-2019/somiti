<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesBanglaInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SettleKistiRequest extends FormRequest
{
    use NormalizesBanglaInput;

    protected $errorBag = 'settle';

    public function authorize(): bool
    {
        return $this->user()?->can('settle', $this->route('account')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeNumbers(['amount']);
    }

    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999'],
            'receiver_id' => ['required', 'integer', Rule::exists('users', 'id')->where('is_active', true)],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'সেটেলমেন্টের টাকা', 'receiver_id' => 'টাকা গ্রহণকারী', 'reason' => 'কারণ'];
    }
}
