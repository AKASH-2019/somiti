<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\NormalizesBanglaInput;
use Illuminate\Foundation\Http\FormRequest;

class ChangeSharesRequest extends FormRequest
{
    use NormalizesBanglaInput;

    protected $errorBag = 'shares';

    public function authorize(): bool
    {
        return $this->user()?->can('changeShares', $this->route('account')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeNumbers(['new_shares']);
    }

    public function rules(): array
    {
        return [
            'new_shares' => ['required', 'integer', 'min:1', 'max:1000'],
            'effective_date' => ['required', 'date', 'after_or_equal:today'],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['new_shares' => 'নতুন শেয়ারের সংখ্যা', 'effective_date' => 'কার্যকর হওয়ার তারিখ'];
    }

    public function messages(): array
    {
        return ['effective_date.after_or_equal' => 'কার্যকর হওয়ার তারিখ আজ বা তার পরের হতে হবে।'];
    }
}
