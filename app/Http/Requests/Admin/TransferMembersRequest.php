<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferMembersRequest extends FormRequest
{
    protected $errorBag = 'transfer';

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'to_collector_id' => ['required', 'integer',
                Rule::exists('users', 'id')->where('role', 'collector')->where('is_active', true),
                Rule::notIn([$this->route('collector')?->id])],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['to_collector_id' => 'নতুন কালেক্টর', 'reason' => 'কারণ'];
    }
}
