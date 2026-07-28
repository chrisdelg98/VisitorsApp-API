<?php

namespace Tests\Feature;

use App\Models\AppRelease;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminAppReleaseTest extends TestCase
{
    use RefreshDatabase;

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin', 'is_active' => true]);
    }

    /**
     * A minimal file that passes the APK check: a real zip containing
     * AndroidManifest.xml. `UploadedFile::fake()->create()` writes null bytes,
     * which is exactly what the validator is there to reject.
     */
    private function fakeApk(string $name = 'app-release.apk'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'apk').'.apk';

        $zip = new \ZipArchive();
        $zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $zip->addFromString('AndroidManifest.xml', 'binary-xml');
        $zip->addFromString('classes.dex', 'dex');
        $zip->close();

        return new UploadedFile($path, $name, 'application/vnd.android.package-archive', null, true);
    }

    /** Drop a real APK straight into the staging directory, as SFTP would. */
    private function stage(string $name, bool $valid = true): string
    {
        $disk    = Storage::disk('local');
        $staging = (string) config('app_updates.staging_path');

        $disk->makeDirectory($staging);

        if (! $valid) {
            $disk->put($staging.'/'.$name, 'not-an-apk');

            return $name;
        }

        $disk->put($staging.'/'.$name, file_get_contents($this->fakeApk()->getRealPath()));

        return $name;
    }

    public function test_requires_authentication(): void
    {
        $this->getJson('/api/v1/admin/app-releases')->assertStatus(401);
    }

    public function test_a_country_manager_cannot_manage_releases(): void
    {
        $user = User::factory()->create(['role' => 'country_manager', 'is_active' => true]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/app-releases')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_uploads_a_release_as_draft(): void
    {
        Storage::fake('local');

        $response = $this->actingAs($this->superAdmin(), 'sanctum')
            ->post('/api/v1/admin/app-releases', [
                'version_code'               => 2,
                'version_name'               => '1.0.1',
                'release_notes'              => 'Corrección en escaneo de DUI.',
                'min_supported_version_code' => 1,
                'apk'                        => $this->fakeApk(),
            ], ['Accept' => 'application/json'])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.version_code', 2)
            ->assertJsonPath('data.file_name', 'visitors-app-v2.apk');

        $release = AppRelease::firstOrFail();

        Storage::disk('local')->assertExists($release->file_path);
        $this->assertSame(64, strlen($release->file_hash));
        $this->assertGreaterThan(0, $release->file_size);
        $this->assertSame($release->file_hash, $response->json('data.file_hash'));
    }

    public function test_a_draft_release_is_not_offered_to_tablets_until_published(): void
    {
        Storage::fake('local');
        $admin = $this->superAdmin();

        $this->actingAs($admin, 'sanctum')
            ->post('/api/v1/admin/app-releases', [
                'version_code' => 2,
                'version_name' => '1.0.1',
                'apk'          => $this->fakeApk(),
            ], ['Accept' => 'application/json'])
            ->assertStatus(201);

        $release = AppRelease::firstOrFail();
        $this->assertNull(AppRelease::latestPublished('android'));

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/v1/admin/app-releases/'.$release->id, ['status' => 'published'])
            ->assertOk()
            ->assertJsonPath('data.status', 'published');

        $this->assertSame(2, AppRelease::latestPublished('android')?->version_code);
        $this->assertNotNull($release->fresh()->published_at);
    }

    public function test_deprecating_a_release_rolls_back_to_the_previous_one(): void
    {
        $admin = $this->superAdmin();
        AppRelease::factory()->published()->create(['version_code' => 3]);
        $bad = AppRelease::factory()->published()->create(['version_code' => 4]);

        $this->actingAs($admin, 'sanctum')
            ->patchJson('/api/v1/admin/app-releases/'.$bad->id, ['status' => 'deprecated'])
            ->assertOk();

        $this->assertSame(3, AppRelease::latestPublished('android')?->version_code);
    }

    public function test_rejects_a_file_that_is_not_an_apk(): void
    {
        Storage::fake('local');

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->post('/api/v1/admin/app-releases', [
                'version_code' => 2,
                'version_name' => '1.0.1',
                'apk'          => UploadedFile::fake()->create('app-release.apk', 512),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors('apk');

        $this->assertSame(0, AppRelease::count());
    }

    public function test_rejects_a_duplicate_version_code_for_the_same_platform(): void
    {
        Storage::fake('local');
        AppRelease::factory()->published()->create(['platform' => 'android', 'version_code' => 2]);

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->post('/api/v1/admin/app-releases', [
                'version_code' => 2,
                'version_name' => '1.0.1',
                'apk'          => $this->fakeApk(),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('version_code');
    }

    public function test_lists_what_is_waiting_in_staging(): void
    {
        Storage::fake('local');
        $this->stage('visitors-1.0.1.apk');
        $this->stage('half-uploaded.apk', valid: false);
        Storage::disk('local')->put((string) config('app_updates.staging_path').'/notes.txt', 'ignored');

        $response = $this->actingAs($this->superAdmin(), 'sanctum')
            ->getJson('/api/v1/admin/app-releases/staged')
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $files = collect($response->json('data'))->keyBy('file_name');

        $this->assertTrue($files['visitors-1.0.1.apk']['is_valid_apk']);
        $this->assertFalse($files['half-uploaded.apk']['is_valid_apk']);
        $this->assertNotEmpty($response->json('staging_path'));
    }

    public function test_registers_a_staged_file_and_moves_it_out_of_staging(): void
    {
        Storage::fake('local');
        $this->stage('visitors-1.0.1.apk');
        $staging = (string) config('app_updates.staging_path');

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->postJson('/api/v1/admin/app-releases', [
                'version_code' => 2,
                'version_name' => '1.0.1',
                'staged_file'  => 'visitors-1.0.1.apk',
            ])
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.file_name', 'visitors-app-v2.apk');

        $release = AppRelease::firstOrFail();

        // Moved, not copied: the next SFTP upload cannot overwrite a release.
        Storage::disk('local')->assertMissing($staging.'/visitors-1.0.1.apk');  // @phpstan-ignore-line
        Storage::disk('local')->assertExists($release->file_path);
        $this->assertStringStartsWith('app-releases/android/', $release->file_path);
        $this->assertSame(64, strlen($release->file_hash));
        $this->assertGreaterThan(0, $release->file_size);
    }

    public function test_a_staged_name_cannot_escape_the_staging_directory(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('app-releases/android/secret.apk', 'already-a-release');

        foreach (['../android/secret.apk', '..\\android\\secret.apk', '/etc/passwd', 'sub/dir.apk'] as $attempt) {
            $this->actingAs($this->superAdmin(), 'sanctum')
                ->postJson('/api/v1/admin/app-releases', [
                    'version_code' => 2,
                    'version_name' => '1.0.1',
                    'staged_file'  => $attempt,
                ])
                ->assertStatus(422)
                ->assertJsonValidationErrors('staged_file');
        }

        $this->assertSame(0, AppRelease::count());
        Storage::disk('local')->assertExists('app-releases/android/secret.apk');
    }

    /**
     * The name pattern already refuses anything with a slash, so this covers
     * the other way out of the directory: a symlink planted inside it.
     */
    public function test_a_symlink_in_staging_cannot_be_registered(): void
    {
        Storage::fake('local');
        $disk    = Storage::disk('local');
        $staging = (string) config('app_updates.staging_path');

        $disk->put('app-releases/android/secret.apk', 'already-a-release');
        $disk->makeDirectory($staging);

        $link = $disk->path($staging.'/link.apk');

        if (! @symlink($disk->path('app-releases/android/secret.apk'), $link)) {
            $this->markTestSkipped('Creating symlinks is not permitted in this environment.');
        }

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->postJson('/api/v1/admin/app-releases', [
                'version_code' => 2,
                'version_name' => '1.0.1',
                'staged_file'  => 'link.apk',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('staged_file');

        Storage::disk('local')->assertExists('app-releases/android/secret.apk');
    }

    public function test_a_partial_upload_in_staging_is_rejected(): void
    {
        Storage::fake('local');
        $this->stage('half-uploaded.apk', valid: false);

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->postJson('/api/v1/admin/app-releases', [
                'version_code' => 2,
                'version_name' => '1.0.1',
                'staged_file'  => 'half-uploaded.apk',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('staged_file');
    }

    public function test_a_staged_file_that_does_not_exist_is_rejected(): void
    {
        Storage::fake('local');

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->postJson('/api/v1/admin/app-releases', [
                'version_code' => 2,
                'version_name' => '1.0.1',
                'staged_file'  => 'nope.apk',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('staged_file');
    }

    public function test_a_binary_is_required_one_way_or_the_other(): void
    {
        Storage::fake('local');

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->postJson('/api/v1/admin/app-releases', [
                'version_code' => 2,
                'version_name' => '1.0.1',
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['apk', 'staged_file']);
    }

    public function test_staging_is_not_reachable_without_super_admin(): void
    {
        $user = User::factory()->create(['role' => 'country_manager', 'is_active' => true]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/v1/admin/app-releases/staged')
            ->assertStatus(403)
            ->assertJsonPath('code', 'FORBIDDEN');
    }

    public function test_a_published_release_cannot_be_deleted(): void
    {
        $release = AppRelease::factory()->published()->create();

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->deleteJson('/api/v1/admin/app-releases/'.$release->id)
            ->assertStatus(422)
            ->assertJsonPath('code', 'RELEASE_PUBLISHED');

        $this->assertDatabaseCount('app_releases', 1);
    }

    public function test_deleting_a_draft_removes_the_binary_too(): void
    {
        Storage::fake('local');
        $release = AppRelease::factory()->create();
        Storage::disk('local')->put($release->file_path, 'apk-bytes');

        $this->actingAs($this->superAdmin(), 'sanctum')
            ->deleteJson('/api/v1/admin/app-releases/'.$release->id)
            ->assertOk();

        Storage::disk('local')->assertMissing($release->file_path);  // @phpstan-ignore-line
        $this->assertDatabaseCount('app_releases', 0);
    }
}
