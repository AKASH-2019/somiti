<?php

namespace App\Http\Requests\Cash;

use App\Http\Requests\Concerns\NormalizesBanglaInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHandoverRequest extends FormRequest
{
    use NormalizesBanglaInput;

    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeNumbers(['amount']);
    }

    public function rules(): array
    {
        return [
            'receiver_id' => ['required', 'integer', Rule::notIn([$this->user()->id]),
                Rule::exists('users', 'id')->where('is_active', true)->whereIn('role', ['admin', 'collector'])],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999'],
            'handover_datetime' => ['required', 'date', 'before:'.now()->addMinutes(10)->toDateTimeString()],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['receiver_id' => 'যাকে টাকা প্রদান করবেন', 'amount' => 'হস্তান্তরের পরিমাণ', 'handover_datetime' => 'হস্তান্তরের তারিখ ও সময়'];
    }

    public function messages(): array
    {
        return [
            'receiver_id.not_in' => 'নিজের কাছে টাকা হস্তান্তর করা যায় না।',
            'handover_datetime.before' => 'হস্তান্তরের সময় ভবিষ্যতের হতে পারবে না।',
        ];
    }
}
