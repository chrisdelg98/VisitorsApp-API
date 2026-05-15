<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreStationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'      => ['required', 'string', 'max:150'],
            'location'  => ['nullable', 'string', 'max:100'],
            'code'      => ['required', 'string', 'max:20', 'unique:stations,code'],
            'pin'       => ['required', 'string', 'min:4', 'max:20'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
