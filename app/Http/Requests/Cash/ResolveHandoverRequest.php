<?php

namespace App\Http\Requests\Cash;

use Illuminate\Foundation\Http\FormRequest;

class ResolveHandoverRequest extends FormRequest
{
    protected $errorBag = 'resolve';

    public function authorize(): bool
    {
        return $this->user()?->can('resolve', $this->route('handover')) ?? false;
    }

    public function rules(): array
    {
        return [
            'outcome' => ['required', 'in:received,rejected'],
            'note' => ['required', 'string', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return ['outcome' => 'সিদ্ধান্ত', 'note' => 'তদন্তের মন্তব্য'];
    }
}
