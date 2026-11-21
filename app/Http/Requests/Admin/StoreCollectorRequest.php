<?php

namespace App\Http\Requests\Admin;

use App\Http\Requests\Concerns\BdMobileRule;
use App\Http\Requests\Concerns\NormalizesBanglaInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class StoreCollectorRequest extends FormRequest
{
    use NormalizesBanglaInput;

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhones(['mobile']);
        $this->normalizeNumbers(['opening_cash']);
        $this->merge(['username' => Str::lower(trim((string) $this->input('username')))]);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'username' => ['required', 'string', 'min:3', 'max:50', 'regex:/^[a-z0-9._-]+$/', 'unique:users,username'],
            'mobile' => ['required', BdMobileRule::REGEX],
            'password' => ['required', 'confirmed', Password::min(8)],
            'opening_cash' => ['nullable', 'numeric', 'min:0', 'max:99999999', 'decimal:0,2'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.regex' => 'ইউজারনেমে শুধু ইংরেজি ছোট অক্ষর, সংখ্যা, ডট (.), হাইফেন (-) ও আন্ডারস্কোর (_) ব্যবহার করুন।',
            'mobile.regex' => BdMobileRule::MESSAGE,
        ];
    }
}
