<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('user')?->id;

        return [
            'name'       => ['sometimes', 'string', 'max:150'],
            'email'      => ['sometimes', 'email', 'max:191', Rule::unique('users', 'email')->ignore($userId)],
            'password'   => ['sometimes', 'string', 'min:8', 'max:191'],
            'role'       => ['sometimes', Rule::in(['admin', 'super_admin', 'country_manager', 'viewer'])],
            'country_id' => ['sometimes', 'nullable', 'uuid', 'exists:countries,id'],
            'is_active'  => ['sometimes', 'boolean'],
        ];
    }
}
