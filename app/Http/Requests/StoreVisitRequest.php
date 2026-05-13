<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'visitor_id'           => ['required', 'uuid', 'exists:visitors,id'],
            'visitor_type'         => ['required', 'string', 'max:50'],
            'visit_reason'         => ['required', 'string', 'max:100'],
            'visit_reason_custom'  => ['nullable', 'required_if:visit_reason,Other', 'string', 'max:255'],
            'visiting_person'      => ['required', 'string', 'max:150'],
            'notes'                => ['nullable', 'string', 'max:2000'],
        ];
    }
}
