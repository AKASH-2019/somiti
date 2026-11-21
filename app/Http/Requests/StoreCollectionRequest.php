<?php

namespace App\Http\Requests;

use App\Enums\EntryType;
use App\Http\Requests\Concerns\NormalizesBanglaInput;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCollectionRequest extends FormRequest
{
    use NormalizesBanglaInput;

    public function authorize(): bool
    {
        return $this->user()?->can('collect', $this->route('account')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $this->normalizeNumbers(['amount']);
        if (! $this->user()?->isAdmin()) {
            $this->merge(['entry_type' => EntryType::Cash->value]); // collectors always record cash received now
        }
    }

    public function rules(): array
    {
        return [
            'keys' => ['required', 'array', 'min:1', 'max:400'],
            'keys.*' => ['required', 'string', 'max:20'],
            'amount' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999'],
            'idempotency_key' => ['required', 'uuid'],
            'notes' => ['nullable', 'string', 'max:500'],
            'entry_type' => ['required', Rule::enum(EntryType::class)],
            'collected_at' => ['nullable', 'required_if:entry_type,historical', 'date', 'before_or_equal:today'],
        ];
    }

    public function attributes(): array
    {
        return ['keys' => 'দিন/কিস্তি', 'amount' => 'টাকার পরিমাণ', 'collected_at' => 'প্রকৃত জমার তারিখ'];
    }

    public function messages(): array
    {
        return [
            'keys.required' => 'অন্তত একটি দিন বা কিস্তি নির্বাচন করুন।',
            'collected_at.required_if' => 'পুরনো খাতার এন্ট্রির জন্য প্রকৃত জমার তারিখ দিন।',
            'collected_at.before_or_equal' => 'প্রকৃত জমার তারিখ আজকের পরে হতে পারবে না।',
        ];
    }
}
