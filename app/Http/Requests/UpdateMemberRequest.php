<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/** Edit member details. Only the admin may change the member ID; collector and status have their own actions. */
class UpdateMemberRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('member')) ?? false;
    }

    protected function prepareForValidation(): void
    {
        $mobile = preg_replace('/[\s\-().]/', '', \App\Support\BanglaFormatter::toAsciiDigits((string) $this->input('mobile')));
        if (preg_match('/^\+?880(1\d{9})$/', $mobile, $m)) {
            $mobile = '0'.$m[1];
        }
        $this->merge(['mobile' => $mobile, 'member_code' => \App\Services\Members\MemberCodeService::normalize($this->input('member_code'))]);
    }

    public function rules(): array
    {
        $rules = StoreMemberRequest::memberRules($this->user(), $this->route('member')->id);
        if (! $this->user()->isAdmin()) {
            unset($rules['member_code']);
        } else {
            $rules['member_code'][0] = 'required';
        }

        return $rules;
    }

    public function messages(): array
    {
        return StoreMemberRequest::memberMessages();
    }

    public function attributes(): array
    {
        return StoreMemberRequest::memberAttributes();
    }
}
