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

    private function frontBlocks(): array
    {
        return [
            ['text' => 'DOCUMENTO UNICO', 'box' => ['x' => 0.1, 'y' => 0.1, 'w' => 0.4, 'h' => 0.05]],
            ['text' => '0000****-1', 'box' => ['x' => 0.6, 'y' => 0.2, 'w' => 0.3, 'h' => 0.05]],
        ];
    }

    private function backBlocks(): array
    {
        return [
            ['text' => 'DIRECCION', 'box' => ['x' => 0.1, 'y' => 0.3, 'w' => 0.5, 'h' => 0.05]],
        ];
    }

    public function test_requires_an_api_key(): void
    {
        $this->postJson('/api/v1/ocr/failed-documents', [
            'front_confidence' => 0.30,
            'front_blocks' => $this->frontBlocks(),
        ])
            ->assertStatus(401)
            ->assertJsonPath('code', 'API_KEY_MISSING');
    }

    public function test_stores_front_and_back_as_a_single_row(): void
    {
        $station = $this->station();

        $response = $this->withHeaders($this->headers($station))
            ->postJson('/api/v1/ocr/failed-documents', [
                'detected_type' => 'SV_DUI',
                'front_confidence' => 0.32,
                'front_blocks' => $this->frontBlocks(),
                'back_blocks' => $this->backBlocks(),
                'app_version' => '1.4.2',
            ])
            ->assertCreated()
            ->assertJsonPath('data.stored', true)
            ->assertJsonStructure(['data' => ['id']]);

        $this->assertSame(1, OcrFailedDocument::count());

        $failed = OcrFailedDocument::findOrFail($response->json('data.id'));

        $this->assertSame($station->id, $failed->station_id);
        $this->assertSame('pending', $failed->status);
        $this->assertSame('SV_DUI', $failed->detected_type);
        $this->assertSame('0.320', (string) $failed->detected_confidence);
        // Both sides live in one row, keyed by side.
        $this->assertCount(2, $failed->ocr_blocks['front']);
        $this->assertCount(1, $failed->ocr_blocks['back']);
        // Privacy: raw text is never stored.
        $this->assertNull($failed->ocr_text);
    }

    public function test_stores_the_apps_reading_and_match_score(): void
    {
        $station = $this->station();

        $response = $this->withHeaders($this->headers($station))
            ->postJson('/api/v1/ocr/failed-documents', [
                'front_confidence' => 0.31,
                'front_blocks' => $this->frontBlocks(),
                'match_score' => 0.51,
                'extracted_fields' => [
                    'first_name' => 'Arevalo Delgado',
                    'document_number' => '05650411-7',
                ],
            ])
            ->assertCreated();

        $failed = OcrFailedDocument::findOrFail($response->json('data.id'));

        $this->assertSame('0.510', (string) $failed->match_score);
        $this->assertSame('Arevalo Delgado', $failed->extracted_fields['first_name']);
        $this->assertSame('05650411-7', $failed->extracted_fields['document_number']);
    }

    public function test_the_apps_reading_is_optional(): void
    {
        $station = $this->station();

        $response = $this->withHeaders($this->headers($station))
            ->postJson('/api/v1/ocr/failed-documents', [
                'front_confidence' => 0.30,
                'front_blocks' => $this->frontBlocks(),
            ])
            ->assertCreated();

        $failed = OcrFailedDocument::findOrFail($response->json('data.id'));
        $this->assertNull($failed->match_score);
        $this->assertNull($failed->extracted_fields);
    }

    public function test_back_blocks_are_optional(): void
    {
        $station = $this->station();

        $response = $this->withHeaders($this->headers($station))
            ->postJson('/api/v1/ocr/failed-documents', [
                'front_confidence' => 0.30,
                'front_blocks' => $this->frontBlocks(),
            ])
            ->assertCreated();

        $failed = OcrFailedDocument::findOrFail($response->json('data.id'));
        $this->assertSame([], $failed->ocr_blocks['back']);
    }

    public function test_low_front_confidence_is_not_stored(): void
    {
        $station = $this->station();

        $this->withHeaders($this->headers($station))
            ->postJson('/api/v1/ocr/failed-documents', [
                'front_confidence' => 0.19,
                'front_blocks' => $this->frontBlocks(),
            ])
            ->assertOk()
            ->assertJsonPath('data.stored', false)
            ->assertJsonPath('data.reason', 'front_confidence_below_threshold');

        $this->assertSame(0, OcrFailedDocument::count());
    }

    public function test_confidence_exactly_at_threshold_is_stored(): void
    {
        $station = $this->station();

        $this->withHeaders($this->headers($station))
            ->postJson('/api/v1/ocr/failed-documents', [
                'front_confidence' => 0.20,
                'front_blocks' => $this->frontBlocks(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.stored', true);

        $this->assertSame(1, OcrFailedDocument::count());
    }

    /**
     * The upper bound is what keeps the review queue useful: above it the app
     * already recognized the document, so the report is not evidence of a
     * missing template.
     */
    public function test_high_front_confidence_is_not_stored(): void
    {
        $station = $this->station();

        $this->withHeaders($this->headers($station))
            ->postJson('/api/v1/ocr/failed-documents', [
                'front_confidence' => 0.45,
                'front_blocks' => $this->frontBlocks(),
            ])
            ->assertOk()
            ->assertJsonPath('data.stored', false)
            ->assertJsonPath('data.reason', 'front_confidence_above_threshold')
            ->assertJsonPath('data.max_confidence', 0.4);

        $this->assertSame(0, OcrFailedDocument::count());
    }

    public function test_confidence_exactly_at_the_upper_bound_is_not_stored(): void
    {
        $this->withHeaders($this->headers($this->station()))
            ->postJson('/api/v1/ocr/failed-documents', [
                'front_confidence' => 0.40,
                'front_blocks' => $this->frontBlocks(),
            ])
            ->assertOk()
            ->assertJsonPath('data.stored', false);

        $this->withHeaders($this->headers($this->station()))
            ->postJson('/api/v1/ocr/failed-documents', [
                'front_confidence' => 0.39,
                'front_blocks' => $this->frontBlocks(),
            ])
            ->assertCreated()
            ->assertJsonPath('data.stored', true);

        $this->assertSame(1, OcrFailedDocument::count());
    }

    public function test_the_band_is_configurable(): void
    {
        config(['ocr.min_front_confidence' => 0.0, 'ocr.max_front_confidence' => 1.0]);

        foreach ([0.01, 0.55, 0.99] as $confidence) {
            $this->withHeaders($this->headers($this->station()))
                ->postJson('/api/v1/ocr/failed-documents', [
                    'front_confidence' => $confidence,
                    'front_blocks' => $this->frontBlocks(),
                ])
                ->assertCreated();
        }

        $this->assertSame(3, OcrFailedDocument::count());
    }

    public function test_station_is_taken_from_the_api_key_not_the_body(): void
    {
        $station = $this->station();
        $other = $this->station();

        $response = $this->withHeaders($this->headers($station))
            ->postJson('/api/v1/ocr/failed-documents', [
                'station_id' => $other->id,
                'front_confidence' => 0.30,
                'front_blocks' => $this->frontBlocks(),
            ])
            ->assertCreated();

        $this->assertSame(
            $station->id,
            OcrFailedDocument::findOrFail($response->json('data.id'))->station_id
        );
    }

    public function test_front_blocks_and_confidence_are_required(): void
    {
        $this->withHeaders($this->headers($this->station()))
            ->postJson('/api/v1/ocr/failed-documents', ['app_version' => '1.4.2'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION_ERROR')
            ->assertJsonValidationErrors(['front_confidence', 'front_blocks']);
    }

    public function test_confidence_outside_zero_to_one_is_rejected(): void
    {
        $this->withHeaders($this->headers($this->station()))
            ->postJson('/api/v1/ocr/failed-documents', [
                'front_confidence' => 1.5,
                'front_blocks' => $this->frontBlocks(),
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('front_confidence');
    }

    public function test_multipart_with_both_images_stores_them_on_the_private_disk(): void
    {
        /** @var \Illuminate\Filesystem\FilesystemAdapter $disk */
        $disk = Storage::fake('local');
        $station = $this->station();

        $response = $this->withHeaders($this->headers($station))
            ->post('/api/v1/ocr/failed-documents', [
                'front_confidence' => 0.30,
                'front_blocks' => json_encode($this->frontBlocks()),
                'back_blocks' => json_encode($this->backBlocks()),
                'extracted_fields' => json_encode(['first_name' => 'Arevalo Delgado']),
                // create() rather than image(): the CI image has no GD extension.
                'front_image' => UploadedFile::fake()->create('front.jpg', 40, 'image/jpeg'),
                'back_image' => UploadedFile::fake()->create('back.jpg', 40, 'image/jpeg'),
            ], ['Accept' => 'application/json'])
            ->assertCreated();

        $failed = OcrFailedDocument::findOrFail($response->json('data.id'));

        $this->assertNotNull($failed->image_path);
        $this->assertNotNull($failed->image_back_path);
        $this->assertStringContainsString('/front-', $failed->image_path);
        $this->assertStringContainsString('/back-', $failed->image_back_path);
        $disk->assertExists($failed->image_path);
        $disk->assertExists($failed->image_back_path);
        // JSON-encoded blocks still land as real arrays.
        $this->assertCount(2, $failed->ocr_blocks['front']);
        // JSON-encoded extracted_fields also decodes to a real array.
        $this->assertSame('Arevalo Delgado', $failed->extracted_fields['first_name']);
    }

    public function test_images_are_optional(): void
    {
        $station = $this->station();

        $response = $this->withHeaders($this->headers($station))
            ->postJson('/api/v1/ocr/failed-documents', [
                'front_confidence' => 0.30,
                'front_blocks' => $this->frontBlocks(),
            ])
            ->assertCreated();

        $failed = OcrFailedDocument::findOrFail($response->json('data.id'));
        $this->assertNull($failed->image_path);
        $this->assertNull($failed->image_back_path);
    }

    public function test_oversized_image_is_rejected(): void
    {
        Storage::fake('local');

        $this->withHeaders($this->headers($this->station()))
            ->post('/api/v1/ocr/failed-documents', [
                'front_confidence' => 0.30,
                'front_blocks' => json_encode($this->frontBlocks()),
                'front_image' => UploadedFile::fake()->create('front.jpg', 6000, 'image/jpeg'),
            ], ['Accept' => 'application/json'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('front_image');
    }

    public function test_legacy_single_side_shape_still_works(): void
    {
        $station = $this->station();

        // Old app: detected_confidence + ocr_blocks (flat) + a single image field.
        $response = $this->withHeaders($this->headers($station))
            ->postJson('/api/v1/ocr/failed-documents', [
                'detected_type' => 'SV_DUI',
                'detected_confidence' => 0.30,
                'ocr_blocks' => $this->frontBlocks(),
            ])
            ->assertCreated();

        $failed = OcrFailedDocument::findOrFail($response->json('data.id'));
        $this->assertCount(2, $failed->ocr_blocks['front']);
        $this->assertSame('0.300', (string) $failed->detected_confidence);
    }

    public function test_pii_fields_are_hidden_from_serialization(): void
    {
        $failed = OcrFailedDocument::create([
            'station_id' => $this->station()->id,
            'ocr_text' => 'JUAN PEREZ 12345678-9',
            'extracted_fields' => ['first_name' => 'JUAN', 'last_name' => 'PEREZ'],
            'image_path' => 'ocr-failed/x/front-y.jpg',
            'image_back_path' => 'ocr-failed/x/back-z.jpg',
            'status' => 'pending',
        ]);

        $array = $failed->toArray();

        $this->assertArrayNotHasKey('ocr_text', $array);
        $this->assertArrayNotHasKey('extracted_fields', $array);
        $this->assertArrayNotHasKey('image_path', $array);
        $this->assertArrayNotHasKey('image_back_path', $array);
    }
}
