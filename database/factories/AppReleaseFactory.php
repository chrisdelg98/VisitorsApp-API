<?php

namespace Database\Factories;

use App\Models\AppRelease;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AppRelease>
 */
class AppReleaseFactory extends Factory
{
    protected $model = AppRelease::class;

    public function definition(): array
    {
        $versionCode = fake()->unique()->numberBetween(1, 5000);

        return [
            'platform'                   => 'android',
            'version_code'               => $versionCode,
            'version_name'               => '1.0.'.$versionCode,
            'status'                     => 'draft',
            'file_path'                  => 'app-releases/android/'.Str::uuid().'.apk',
            'file_name'                  => 'visitors-app-v'.$versionCode.'.apk',
            'file_hash'                  => hash('sha256', (string) $versionCode),
            'file_size'                  => 157286400,
            'release_notes'              => 'Correcciones varias.',
            'min_supported_version_code' => 0,
            'is_critical'                => false,
            'published_at'               => null,
            'created_by'                 => null,
        ];
    }

    public function published(): static
    {
        return $this->state(fn () => [
            'status'       => 'published',
            'published_at' => now(),
        ]);
    }

    public function deprecated(): static
    {
        return $this->state(fn () => ['status' => 'deprecated']);
    }
}
