<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class ReverseTransactionRequest extends FormRequest
{
    protected $errorBag = 'reverse';

    public function authorize(): bool
    {
        return $this->user()?->can('reverse', $this->route('transaction')) ?? false;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:255']];
    }

    public function attributes(): array
    {
        return ['reason' => 'বাতিলের কারণ'];
    }
}
