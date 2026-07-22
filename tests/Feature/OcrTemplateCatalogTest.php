<?php

namespace Tests\Feature;

use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class OcrTemplateCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function station(): Station
    {
        return Station::factory()->create();
    }

    private function headers(Station $station, array $extra = []): array
    {
        return array_merge(['X-API-Key' => $station->api_key], $extra);
    }

    public function test_requires_an_api_key(): void
    {
        $this->getJson('/api/v1/ocr/templates')
            ->assertStatus(401)
            ->assertJsonPath('code', 'API_KEY_MISSING');
    }

    public function test_returns_only_published_templates_with_the_type_embedded(): void
    {
        $station = $this->station();
        $type = DocumentType::factory()->create([
            'code' => 'SV_DUI',
            'document_kind' => 'id_card',
            'subdivision' => null,
        ]);

        DocumentTemplate::factory()->for($type, 'documentType')->published()->create(['side' => 'front']);
        DocumentTemplate::factory()->for($type, 'documentType')->create(['side' => 'back']);          // draft
        DocumentTemplate::factory()->for($type, 'documentType')->deprecated()->create([
            'version' => '2010',
        ]);

        $response = $this->withHeaders($this->headers($station))
            ->getJson('/api/v1/ocr/templates')
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('count', 1)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.code', 'SV_DUI')
            ->assertJsonPath('data.0.document_kind', 'id_card')
            ->assertJsonPath('data.0.side', 'front');

        $this->assertIsInt($response->json('catalog_version'));
        $this->assertIsArray($response->json('data.0.fields'));
        $this->assertIsArray($response->json('data.0.signature'));
    }

    public function test_templates_of_an_inactive_type_are_not_served(): void
    {
        $station = $this->station();
        $type = DocumentType::factory()->inactive()->create();
        DocumentTemplate::factory()->for($type, 'documentType')->published()->create();

        $this->withHeaders($this->headers($station))
            ->getJson('/api/v1/ocr/templates')
            ->assertOk()
            ->assertJsonPath('count', 0);
    }

    public function test_catalog_version_is_the_max_updated_at_and_is_sent_as_etag(): void
    {
        $station = $this->station();

        Carbon::setTestNow('2026-06-02 10:00:00');
        $type = DocumentType::factory()->create();
        DocumentTemplate::factory()->for($type, 'documentType')->published()->create();
        Carbon::setTestNow();

        $expected = Carbon::parse('2026-06-02 10:00:00')->getTimestamp();

        $this->withHeaders($this->headers($station))
            ->getJson('/api/v1/ocr/templates')
            ->assertOk()
            ->assertJsonPath('catalog_version', $expected)
            ->assertHeader('ETag', '"'.$expected.'"');
    }

    public function test_matching_if_none_match_returns_304_without_body(): void
    {
        $station = $this->station();
        $type = DocumentType::factory()->create();
        DocumentTemplate::factory()->for($type, 'documentType')->published()->create();

        $first = $this->withHeaders($this->headers($station))->getJson('/api/v1/ocr/templates')->assertOk();
        $etag = $first->headers->get('ETag');

        $second = $this->withHeaders($this->headers($station, ['If-None-Match' => $etag]))
            ->getJson('/api/v1/ocr/templates')
            ->assertStatus(304);

        $this->assertSame('', $second->getContent());
        $this->assertSame($etag, $second->headers->get('ETag'));
    }

    public function test_a_stale_if_none_match_returns_the_full_catalog(): void
    {
        $station = $this->station();
        $type = DocumentType::factory()->create();
        DocumentTemplate::factory()->for($type, 'documentType')->published()->create();

        $this->withHeaders($this->headers($station, ['If-None-Match' => '"1"']))
            ->getJson('/api/v1/ocr/templates')
            ->assertOk()
            ->assertJsonPath('count', 1);
    }

    public function test_touching_the_document_type_bumps_the_catalog_version(): void
    {
        $station = $this->station();

        Carbon::setTestNow('2026-06-02 10:00:00');
        $type = DocumentType::factory()->create();
        DocumentTemplate::factory()->for($type, 'documentType')->published()->create();
        Carbon::setTestNow();

        $before = $this->withHeaders($this->headers($station))
            ->getJson('/api/v1/ocr/templates')->json('catalog_version');

        Carbon::setTestNow('2026-06-03 09:00:00');
        $type->update(['name' => 'DUI - El Salvador (2024)']);
        Carbon::setTestNow();

        $after = $this->withHeaders($this->headers($station))
            ->getJson('/api/v1/ocr/templates')->json('catalog_version');

        $this->assertGreaterThan($before, $after);
    }

    public function test_deprecating_a_template_bumps_the_catalog_version(): void
    {
        $station = $this->station();

        Carbon::setTestNow('2026-06-02 10:00:00');
        $type = DocumentType::factory()->create();
        $template = DocumentTemplate::factory()->for($type, 'documentType')->published()->create();
        Carbon::setTestNow();

        $before = $this->withHeaders($this->headers($station))
            ->getJson('/api/v1/ocr/templates')->json('catalog_version');

        Carbon::setTestNow('2026-06-03 09:00:00');
        $template->update(['status' => 'deprecated']);
        Carbon::setTestNow();

        $response = $this->withHeaders($this->headers($station))->getJson('/api/v1/ocr/templates');

        $this->assertGreaterThan($before, $response->json('catalog_version'));
        $this->assertSame(0, $response->json('count'));
    }

    public function test_empty_catalog_is_served_with_version_zero(): void
    {
        $this->withHeaders($this->headers($this->station()))
            ->getJson('/api/v1/ocr/templates')
            ->assertOk()
            ->assertJsonPath('catalog_version', 0)
            ->assertJsonPath('count', 0);
    }
}
