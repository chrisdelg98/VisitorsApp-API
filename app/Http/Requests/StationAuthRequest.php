<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StationAuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'pin'               => ['required', 'string', 'digits:8'],
            'device_imei'       => ['nullable', 'string', 'max:20'],
            'device_android_id' => ['nullable', 'string', 'max:64'],
            'device_model'      => ['nullable', 'string', 'max:100'],
            'device_ip'         => ['nullable', 'ip'],
        ];
    }
}
