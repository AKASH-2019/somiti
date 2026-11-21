<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreHolidayRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'date' => ['required', 'date', 'unique:holidays,date'],
            'title' => ['required', 'string', 'max:150'],
        ];
    }

    public function messages(): array
    {
        return ['date.unique' => 'এই তারিখে আগে থেকেই একটি ছুটি আছে।'];
    }
}
