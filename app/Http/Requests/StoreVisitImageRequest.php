<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVisitImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type'  => ['required', Rule::in(['personal_photo', 'doc_front', 'doc_back'])],
            // 5 MB hard cap (per security plan). MIME validated against the actual file contents.
            'image' => [
                'required',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:5120',
            ],
        ];
    }
}
