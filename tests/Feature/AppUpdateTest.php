<?php

namespace Tests\Feature;

use App\Models\AppRelease;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AppUpdateTest extends TestCase
{
    use RefreshDatabase;

    private function station(): Station
    {
        return Station::factory()->create();
    }

    /** @param array<string, string> $extra */
    private function headers(Station $station, array $extra = []): array
    {
        return array_merge(['X-API-Key' => $station->api_key], $extra);
    }

    /** A published release whose binary actually exists on the faked disk. */
    private function releaseWithBinary(array $attributes = []): AppRelease
    {
        Storage::fake('local');

        $release = AppRelease::factory()->published()->create($attributes);
        Storage::disk('local')->put($release->file_path, 'apk-bytes');

        return $release;
    }

    public function test_requires_an_api_key(): void
    {
        $this->getJson('/api/v1/app/latest')
            ->assertStatus(401)
            ->assertJsonPath('code', 'API_KEY_MISSING');
    }

    public function test_returns_null_when_nothing_is_published(): void
    {
        AppRelease::factory()->create(['version_code' => 3]);            // draft
        AppRelease::factory()->deprecated()->create(['version_code' => 2]);

        $this->withHeaders($this->headers($this->station()))
            ->getJson('/api/v1/app/latest')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data', null)
            ->assertJsonPath('update_available', false)
            ->assertJsonPath('update_required', false);
    }

    public function test_serves_the_highest_published_version(): void
    {
        $station = $this->station();

        AppRelease::factory()->published()->create(['version_code' => 2, 'version_name' => '1.0.1']);
        AppRelease::factory()->published()->create(['version_code' => 4, 'version_name' => '1.0.3']);
        AppRelease::factory()->create(['version_code' => 5, 'version_name' => '1.0.4']);      // draft
        AppRelease::factory()->deprecated()->create(['version_code' => 6]);                   // rolled back

        $this->withHeaders($this->headers($station))
            ->getJson('/api/v1/app/latest?current_version_code=2')
            ->assertOk()
            ->assertJsonPath('data.version_code', 4)
            ->assertJsonPath('data.version_name', '1.0.3')
            ->assertJsonPath('update_available', true)
            ->assertJsonPath('update_required', false);
    }

    public function test_a_device_on_the_latest_version_gets_no_update(): void
    {
        AppRelease::factory()->published()->create(['version_code' => 4]);

        $this->withHeaders($this->headers($this->station()))
            ->getJson('/api/v1/app/latest?current_version_code=4')
            ->assertOk()
            ->assertJsonPath('data.version_code', 4)
            ->assertJsonPath('update_available', false)
            ->assertJsonPath('update_required', false);
    }

    public function test_update_is_required_below_the_minimum_supported_version(): void
    {
        AppRelease::factory()->published()->create([
            'version_code'               => 5,
            'min_supported_version_code' => 4,
            'is_critical'                => false,
        ]);

        $this->withHeaders($this->headers($this->station()))
            ->getJson('/api/v1/app/latest?current_version_code=3')
            ->assertOk()
            ->assertJsonPath('update_required', true);

        $this->withHeaders($this->headers($this->station()))
            ->getJson('/api/v1/app/latest?current_version_code=4')
            ->assertOk()
            ->assertJsonPath('update_available', true)
            ->assertJsonPath('update_required', false);
    }

    public function test_a_critical_release_is_required_for_everyone_behind_it(): void
    {
        AppRelease::factory()->published()->create([
            'version_code'               => 5,
            'min_supported_version_code' => 0,
            'is_critical'                => true,
        ]);

        $this->withHeaders($this->headers($this->station()))
            ->getJson('/api/v1/app/latest?current_version_code=4')
            ->assertOk()
            ->assertJsonPath('update_required', true);
    }

    public function test_a_device_that_does_not_report_its_version_is_never_forced(): void
    {
        AppRelease::factory()->published()->create([
            'version_code'               => 5,
            'min_supported_version_code' => 99,
            'is_critical'                => false,
        ]);

        $this->withHeaders($this->headers($this->station()))
            ->getJson('/api/v1/app/latest')
            ->assertOk()
            ->assertJsonPath('update_available', true)
            ->assertJsonPath('update_required', false);
    }

    public function test_matching_if_none_match_returns_304_without_body(): void
    {
        $station = $this->station();
        AppRelease::factory()->published()->create(['version_code' => 4]);

        $first = $this->withHeaders($this->headers($station))
            ->getJson('/api/v1/app/latest?current_version_code=2')
            ->assertOk();

        $etag = $first->headers->get('ETag');

        $second = $this->withHeaders($this->headers($station, ['If-None-Match' => $etag]))
            ->getJson('/api/v1/app/latest?current_version_code=2')
            ->assertStatus(304);

        $this->assertSame('', $second->getContent());
        $this->assertSame($etag, $second->headers->get('ETag'));
    }

    public function test_a_device_that_just_updated_is_not_answered_from_its_old_etag(): void
    {
        $station = $this->station();
        AppRelease::factory()->published()->create(['version_code' => 4]);

        $etag = $this->withHeaders($this->headers($station))
            ->getJson('/api/v1/app/latest?current_version_code=2')
            ->assertOk()
            ->headers->get('ETag');

        // Same release, but the device now runs it — a 304 here would keep
        // telling the tablet an update is available forever.
        $this->withHeaders($this->headers($station, ['If-None-Match' => $etag]))
            ->getJson('/api/v1/app/latest?current_version_code=4')
            ->assertOk()
            ->assertJsonPath('update_available', false);
    }

    public function test_download_url_points_at_the_authenticated_endpoint(): void
    {
        $release = $this->releaseWithBinary(['version_code' => 4]);

        $url = $this->withHeaders($this->headers($this->station()))
            ->getJson('/api/v1/app/latest')
            ->assertOk()
            ->json('data.download_url');

        $this->assertStringContainsString('/api/v1/app/releases/'.$release->id.'/download', $url);
        $this->assertStringNotContainsString('/storage/', $url);
    }

    public function test_download_requires_an_api_key(): void
    {
        $release = $this->releaseWithBinary();

        $this->get('/api/v1/app/releases/'.$release->id.'/download')
            ->assertStatus(401);
    }

    public function test_download_serves_the_apk(): void
    {
        $release = $this->releaseWithBinary();

        $response = $this->withHeaders($this->headers($this->station()))
            ->get('/api/v1/app/releases/'.$release->id.'/download')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/vnd.android.package-archive');

        $this->assertSame('apk-bytes', $response->streamedContent());
    }

    public function test_download_supports_range_requests_so_it_can_resume(): void
    {
        $release = $this->releaseWithBinary();

        $this->withHeaders($this->headers($this->station(), ['Range' => 'bytes=4-']))
            ->get('/api/v1/app/releases/'.$release->id.'/download')
            ->assertStatus(206)
            ->assertHeader('Content-Range', 'bytes 4-8/9');
    }

    public function test_an_unpublished_release_cannot_be_downloaded(): void
    {
        Storage::fake('local');
        $release = AppRelease::factory()->create();          // draft
        Storage::disk('local')->put($release->file_path, 'apk-bytes');

        $this->withHeaders($this->headers($this->station()))
            ->getJson('/api/v1/app/releases/'.$release->id.'/download')
            ->assertStatus(404)
            ->assertJsonPath('code', 'RELEASE_NOT_PUBLISHED');
    }

    public function test_a_missing_binary_reports_a_clear_error(): void
    {
        Storage::fake('local');
        $release = AppRelease::factory()->published()->create();

        $this->withHeaders($this->headers($this->station()))
            ->getJson('/api/v1/app/releases/'.$release->id.'/download')
            ->assertStatus(404)
            ->assertJsonPath('code', 'RELEASE_FILE_MISSING');
    }
}
