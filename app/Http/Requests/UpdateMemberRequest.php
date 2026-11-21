<?php

namespace App\Http\Requests;

class UpdateMemberRequest extends StoreMemberRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('member')) ?? false;
    }

    /** Collector and status are changed through their own admin actions, not this form. */
    public function rules(): array
    {
        $rules = parent::rules();
        unset($rules['collector_id']);

        return $rules;
    }
}
