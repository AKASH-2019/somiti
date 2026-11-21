<?php

namespace App\Http\Requests\Cash;

use Illuminate\Foundation\Http\FormRequest;

/** A required reason, in its own error bag so the right modal re-opens. Subclasses set the bag and ability. */
abstract class ReasonRequest extends FormRequest
{
    protected string $ability;

    public function authorize(): bool
    {
        return $this->user()?->can($this->ability, $this->route('handover')) ?? false;
    }

    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:255']];
    }

    public function attributes(): array
    {
        return ['reason' => 'কারণ'];
    }
}
