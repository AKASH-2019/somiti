<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'org_name' => ['required', 'string', 'max:150'],
            'org_address' => ['nullable', 'string', 'max:255'],
            'org_mobile' => ['nullable', 'string', 'max:20'],
            'share_value' => ['required', 'numeric', 'decimal:0,2', 'min:1', 'max:100000'],
            'weekly_off_days' => ['nullable', 'array'],
            'weekly_off_days.*' => ['integer', 'between:0,6'],
            'receipt_footer' => ['nullable', 'string', 'max:255'],
        ];
    }
}
