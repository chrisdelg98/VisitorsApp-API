<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreOcrFailedDocumentRequest extends FormRequest
{
    /** Max serialized size of the ocr_blocks payload (256 KB). */
    private const MAX_BLOCKS_BYTES = 262144;

    public function authorize(): bool
    {
        return true;
    }

    /**
     * On multipart uploads `ocr_blocks` arrives as a JSON string; decode it so
     * the array rules below apply to the same structure in both transports.
     */
    protected function prepareForValidation(): void
    {
        $blocks = $this->input('ocr_blocks');

        if (is_string($blocks) && $blocks !== '') {
            $decoded = json_decode($blocks, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['ocr_blocks' => $decoded]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'detected_type'       => ['nullable', 'string', 'max:50'],
            'detected_confidence' => ['nullable', 'numeric', 'between:0,1'],

            // Structured OCR output — preferred over the raw image for privacy.
            'ocr_blocks'          => ['nullable', 'array', 'max:500'],
            'ocr_blocks.*.text'   => ['nullable', 'string', 'max:500'],
            'ocr_blocks.*.box'    => ['nullable', 'array'],

            // PII. Optional and expected to arrive masked from the device.
            'ocr_text'            => ['nullable', 'string', 'max:20000'],

            // Same 5 MB cap and content-based MIME check as visit images.
            'image'               => [
                'nullable',
                'file',
                'image',
                'mimes:jpeg,jpg,png,webp',
                'mimetypes:image/jpeg,image/png,image/webp',
                'max:5120',
            ],

            'app_version'         => ['nullable', 'string', 'max:30'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                // A report with no evidence at all is not reviewable.
                if (blank($this->input('ocr_blocks')) && blank($this->input('ocr_text')) && ! $this->hasFile('image')) {
                    $validator->errors()->add(
                        'ocr_blocks',
                        'At least one of ocr_blocks, ocr_text or image must be provided.'
                    );
                }

                $blocks = $this->input('ocr_blocks');

                if (is_array($blocks) && strlen((string) json_encode($blocks)) > self::MAX_BLOCKS_BYTES) {
                    $validator->errors()->add('ocr_blocks', 'The ocr_blocks payload exceeds the 256 KB limit.');
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'ocr_blocks' => 'OCR blocks',
            'ocr_text'   => 'OCR text',
        ];
    }
}
