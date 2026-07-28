<?php

namespace App\Http\Requests\Admin;

use App\Services\AppReleaseService;
use App\Support\ApkFile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * A release binary arrives one of two ways:
 *
 *   `apk`         — multipart upload, convenient for small/dev builds.
 *   `staged_file` — the name of a file already dropped in the staging
 *                   directory by SFTP, which is how a ~150 MB build gets in
 *                   without fighting PHP upload limits.
 *
 * Exactly one of them is required.
 */
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

            'apk' => [
                'required_without:staged_file',
                'prohibits:staged_file',
                'file',
                'max:'.(int) config('app_updates.max_apk_size_kb'),
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null) {
                        return;                       // absence is required_without's job
                    }

                    if (! $value instanceof UploadedFile || ! ApkFile::isValid($value->getRealPath())) {
                        $fail('The uploaded file is not a valid Android APK.');
                    }
                },
            ],

            'staged_file' => [
                'required_without:apk',
                'string',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    // resolveStagedPath() refuses anything that is not a plain
                    // file sitting directly in the staging directory, so a
                    // traversal attempt fails here rather than being registered.
                    $path = app(AppReleaseService::class)->resolveStagedPath((string) $value);

                    if ($path === null) {
                        $fail('No such file in the release staging directory.');

                        return;
                    }

                    if (! ApkFile::isValid($path)) {
                        $fail('The staged file is not a valid Android APK — it may be a partial upload.');
                    }
                },
            ],

            'release_notes' => ['nullable', 'string', 'max:2000'],

            'min_supported_version_code' => ['nullable', 'integer', 'min:0'],

            'is_critical' => ['sometimes', 'boolean'],
        ];
    }
}
