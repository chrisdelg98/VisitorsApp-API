<?php

namespace App\Http\Requests\Admin;

use App\Models\Station;
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
            'pin'       => [
                'required',
                'string',
                'digits:8',
                function (string $attr, mixed $value, \Closure $fail): void {
                    $lookup = Station::makePinLookup($value);
                    if (Station::where('pin_lookup', $lookup)->exists()) {
                        $fail('This PIN is already assigned to another station.');
                    }
                },
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
