<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

class StoreAppReleaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('platform')) {
            $this->merge(['platform' => (string) config('app_updates.default_platform')]);
        }
    }

    public function rules(): array
    {
        return [
            'platform' => ['sometimes', 'string', 'max:20'],

            'version_code' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('app_releases', 'version_code')
                    ->where('platform', (string) $this->input('platform')),
            ],

            'version_name' => ['required', 'string', 'max:30'],

            // Android ships an APK as a ZIP container; the browser/curl MIME is
            // unreliable, so the real check is the archive contents below.
            'apk' => [
                'required',
                'file',
                'max:'.(int) config('app_updates.max_apk_size_kb'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (! $value instanceof UploadedFile || ! $this->looksLikeApk($value)) {
                        $fail('The uploaded file is not a valid Android APK.');
                    }
                },
            ],

            'release_notes' => ['nullable', 'string', 'max:2000'],

            'min_supported_version_code' => ['nullable', 'integer', 'min:0'],

            'is_critical' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * An APK is a ZIP whose root contains AndroidManifest.xml. Checking the
     * magic bytes alone would accept any zip, so we look inside when the zip
     * extension is available and fall back to the signature when it is not.
     */
    private function looksLikeApk(UploadedFile $file): bool
    {
        $path   = $file->getRealPath();
        $handle = $path ? @fopen($path, 'rb') : false;

        if ($handle === false) {
            return false;
        }

        $magic = (string) fread($handle, 4);
        fclose($handle);

        if ($magic !== "PK\x03\x04") {
            return false;
        }

        if (! class_exists(\ZipArchive::class)) {
            return true;
        }

        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            return false;
        }

        $hasManifest = $zip->locateName('AndroidManifest.xml') !== false;
        $zip->close();

        return $hasManifest;
    }
}
