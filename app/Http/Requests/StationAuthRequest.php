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
            'station_code' => ['required', 'string', 'max:20'],
            'pin'          => ['required', 'string', 'min:4', 'max:20'],
        ];
    }
}
