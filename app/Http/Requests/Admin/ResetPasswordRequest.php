<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends FormRequest
{
    /** Separate error bag so the reset modal re-opens with its own errors. */
    protected $errorBag = 'resetPassword';

    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return ['password' => ['required', 'confirmed', Password::min(8)]];
    }
}
