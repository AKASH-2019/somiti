<?php

namespace App\Http\Requests\Cash;

use App\Http\Requests\Concerns\NormalizesBanglaInput;
use Illuminate\Foundation\Http\FormRequest;

class CashAdjustmentRequest extends FormRequest
{
    use NormalizesBanglaInput;

    protected $errorBag = 'adjust';

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeNumbers(['amount']);
    }

    public function rules(): array
    {
        return [
            'direction' => ['required', 'in:in,out'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999'],
            'reason' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['direction' => 'ধরন', 'amount' => 'পরিমাণ', 'reason' => 'কারণ'];
    }
}
