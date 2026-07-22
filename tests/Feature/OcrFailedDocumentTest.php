<?php

namespace Tests\Feature;

use App\Models\OcrFailedDocument;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OcrFailedDocumentTest extends TestCase
{
    use RefreshDatabase;

    private function station(): Station
    {
        return Station::factory()->create();
    }

    private function headers(Station $station): array
    {
        return ['X-API-Key' => $station->api_key];
    }

    private function blocks(): array
    {
        return [
            ['text' => 'DOCUMENTO UNICO', 'box' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.4, 'h' => 0.05]],
            ['text' => '0000****-1', 'box' => ['x' => 0.6, 'y' => 0.2, 'w' => 0.3, 'h' => 0.05]],
        ];
    }

    public function test_requires_an_api_key(): void
    {
        $this->postJson('/api/v1/ocr/failed-documents', ['ocr_blocks' => $this->blocks()])
            ->assertStatus(401)
            ->assertJsonPath('code', 'API_KEY_MISSING');
    }

    public function test_stores_a_report_as_pending_for_the_authenticated_station(): void
    {
        $station = $this->station();

        $response = $this->withHeaders($this->headers($station))
            ->postJson('/api/v1/ocr/failed-documents', [
                'detected_type' => 'SV_DUI',
                'detected_confidence' => 0.421,
                'ocr_blocks' => $this->blocks(),
                'app_version' => '1.4.2',
            ])
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['id']]);

        $failed = OcrFailedDocument::findOrFail($response->json('data.id'));

        $this->assertSame($station->id, $failed->station_id);
        $this->assertSame('pending', $failed->status);
        $this->assertSame('SV_DUI', $failed->detected_type);
        $this->assertSame('0.421', (string) $failed->detected_confidence);
        $this->assertCount(2, $failed->ocr_blocks);
        $this->assertNull($failed->image_path);
    }

    public function test_station_is_taken_from_the_api_key_not_the_body(): void
    {
        $station = $this->station();
        $other = $this->station();

        $response = $this->withHeaders($this->headers($station))
            ->postJson('/api/v1/ocr/failed-documents', [
                'station_id' => $other->id,
                'ocr_blocks' => $this->blocks(),
            ])
            ->assertCreated();

        $this->assertSame(
            $station->id,
            OcrFailedDocument::findOrFail($response->json('data.id'))->station_id
        );
    }

    public function test_a_report_with_no_evidence_is_rejected(): void
    {
        $this->withHeaders($this->headers($this->station()))
            ->postJson('/api/v1/ocr/failed-documents', ['app_version' => '1.4.2'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors('ocr_blocks');
    }

    public function test_confidence_outside_zero_to_one_is_rejected(): void
    {
        $this->withHeaders($this->headers($this->station()))
            ->postJson('/api/v1/ocr/failed-documents', [
                'ocr_blocks' => $this->blocks(),
                'detected_confidence' => 1.5,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('detected_confidence');
    }

    public function test_multipart_report_with_image_stores_it_on_the_private_disk(): void
    {
        Storage::fake('local');
        $station = $this->station();

        $response = $this->withHeaders($this->headers($station))
            ->post('/api/v1/ocr/failed-documents', [
                'ocr_blocks' => json_encode($this->blocks()),
                // create() rather than image(): the CI image has no GD extension.
                'image' => UploadedFile::fake()->create('doc.jpg', 40, 'image/jpeg'),
                'app_version' => '1.4.2',
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $failed = OcrFailedDocument::findOrFail($response->json('data.id'));

        $this->assertNotNull($failed->image_path);
        $this->assertStringStartsWith('ocr-failed/'.$failed->id.'/', $failed->image_path);
        Storage::disk('local')->assertExists($failed->image_path);

        // The JSON-encoded blocks must still land as a real array.
        $this->assertCount(2, $failed->ocr_blocks);
    }

    public function test_oversized_image_is_rejected(): void
    {
        Storage::fake('local');

        $this->withHeaders($this->headers($this->station()))
            ->post('/api/v1/ocr/failed-documents', [
                'ocr_blocks' => json_encode($this->blocks()),
                'image' => UploadedFile::fake()->create('doc.jpg', 6000, 'image/jpeg'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('image');
    }

    public function test_pii_fields_are_hidden_from_serialization(): void
    {
        $failed = OcrFailedDocument::create([
            'station_id' => $this->station()->id,
            'ocr_text' => 'JUAN PEREZ 12345678-9',
            'image_path' => 'ocr-failed/x/y.jpg',
            'status' => 'pending',
        ]);

        $array = $failed->toArray();

        $this->assertArrayNotHasKey('ocr_text', $array);
        $this->assertArrayNotHasKey('image_path', $array);
    }
}
