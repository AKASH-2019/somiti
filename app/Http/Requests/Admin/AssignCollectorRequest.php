<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AssignCollectorRequest extends FormRequest
{
    protected $errorBag = 'assign';

    public function authorize(): bool
    {
        return $this->user()?->can('assign', $this->route('member')) ?? false;
    }

    public function rules(): array
    {
        return [
            'collector_id' => ['required', 'integer',
                Rule::exists('users', 'id')->where('role', 'collector')->where('is_active', true)],
            'reason' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['collector_id' => 'কালেক্টর', 'reason' => 'কারণ'];
    }
}
