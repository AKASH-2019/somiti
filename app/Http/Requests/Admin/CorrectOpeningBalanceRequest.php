<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesBanglaInput;
use Illuminate\Foundation\Http\FormRequest;

class CorrectOpeningBalanceRequest extends FormRequest
{
    use NormalizesBanglaInput;

    protected $errorBag = 'opening';

    public function authorize(): bool
    {
        return $this->user()?->can('correctOpeningBalance', $this->route('account')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeNumbers(['opening_balance']);
    }

    public function rules(): array
    {
        return [
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['opening_balance' => 'প্রারম্ভিক সঞ্চয়', 'reason' => 'সংশোধনের কারণ'];
    }
}
