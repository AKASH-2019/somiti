<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\BdMobileRule;
use App\Http\Requests\Concerns\NormalizesBanglaInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UpdateCollectorRequest extends FormRequest
{
    use NormalizesBanglaInput;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhones(['mobile']);
        $this->merge(['username' => Str::lower(trim((string) $this->input('username')))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/',
                Rule::unique('users', 'username')->ignore($this->route('collector'))],
            'mobile' => ['required', BdMobileRule::REGEX],
        ];
    }

    public function messages(): array
    {
        return (new StoreCollectorRequest)->messages();
    }
}
