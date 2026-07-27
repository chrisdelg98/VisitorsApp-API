<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Unified OCR failure report: ONE request carries both sides of a document.
 *
 * Canonical shape (works as application/json or multipart/form-data):
 *   detected_type     string   optional  e.g. "SV_DUI"
 *   app_version       string   optional
 *   front_confidence  number   required  classifier confidence 0..1 (FRONT only)
 *   front_blocks      array    required  [ { text, box:{x,y,w,h} }, ... ]  (0..1 coords)
 *   back_blocks       array    optional  same shape as front_blocks
 *   front_image       file     optional  jpeg/png/webp, <= 5 MB
 *   back_image        file     optional  jpeg/png/webp, <= 5 MB
 *   match_score       number   optional  the app's own match confidence 0..1
 *   extracted_fields  object   optional  the app's reading, { field: value, ... }
 *
 * On multipart, array fields arrive as JSON strings and are decoded here.
 * `ocr_text` is never accepted from the client: PII stays out by policy, the
 * text lives inside each block's `text`.
 */
class StoreOcrFailedDocumentRequest extends FormRequest
{
    /** Max serialized size of a single side's blocks payload (256 KB). */
    private const MAX_BLOCKS_BYTES = 262144;

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Multipart sends arrays as JSON strings — decode them to real arrays.
        foreach (['front_blocks', 'back_blocks', 'ocr_blocks', 'extracted_fields'] as $key) {
            $value = $this->input($key);

            if (is_string($value) && $value !== '') {
                $decoded = json_decode($value, true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    $this->merge([$key => $decoded]);
                }
            }
        }

        // Backward compatibility with the old single-side shape
        // (detected_confidence + ocr_blocks): map it onto the front fields so a
        // not-yet-updated app keeps working during the rollout.
        $legacy = [];

        if ($this->input('front_confidence') === null && $this->input('detected_confidence') !== null) {
            $legacy['front_confidence'] = $this->input('detected_confidence');
        }
        if ($this->input('front_blocks') === null && $this->input('ocr_blocks') !== null) {
            $legacy['front_blocks'] = $this->input('ocr_blocks');
        }
        if ($legacy !== []) {
            $this->merge($legacy);
        }
    }

    public function rules(): array
    {
        $imageRules = [
            'nullable', 'file', 'image',
            'mimes:jpeg,jpg,png,webp',
            'mimetypes:image/jpeg,image/png,image/webp',
            'max:5120',
        ];

        return [
            'detected_type'    => ['nullable', 'string', 'max:50'],
            'app_version'      => ['nullable', 'string', 'max:30'],

            // FRONT — the only side that gates acceptance.
            'front_confidence'    => ['required', 'numeric', 'between:0,1'],
            'front_blocks'        => ['required', 'array', 'min:1', 'max:500'],
            'front_blocks.*.text' => ['nullable', 'string', 'max:500'],
            'front_blocks.*.box'  => ['nullable', 'array'],

            // BACK — optional context, never gates acceptance.
            'back_blocks'        => ['nullable', 'array', 'max:500'],
            'back_blocks.*.text' => ['nullable', 'string', 'max:500'],
            'back_blocks.*.box'  => ['nullable', 'array'],

            // The app's own reading, shown in the review queue. Never gates
            // acceptance; match_score is informational, extracted_fields is PII.
            'match_score'      => ['nullable', 'numeric', 'between:0,1'],
            'extracted_fields' => ['nullable', 'array', 'max:100'],

            'front_image' => $imageRules,
            'back_image'  => $imageRules,
            // Legacy single-image field, treated as the front image.
            'image'       => $imageRules,
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                foreach (['front_blocks', 'back_blocks'] as $key) {
                    $blocks = $this->input($key);

                    if (is_array($blocks) && strlen((string) json_encode($blocks)) > self::MAX_BLOCKS_BYTES) {
                        $validator->errors()->add($key, "The {$key} payload exceeds the 256 KB limit.");
                    }
                }
            },
        ];
    }

    public function attributes(): array
    {
        return [
            'front_confidence' => 'front confidence',
            'front_blocks'     => 'front OCR blocks',
            'back_blocks'      => 'back OCR blocks',
        ];
    }
}
