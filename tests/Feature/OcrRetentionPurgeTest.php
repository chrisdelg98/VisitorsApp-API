<?php

namespace Tests\Feature;

use App\Models\OcrFailedDocument;
use App\Models\Station;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class OcrRetentionPurgeTest extends TestCase
{
    use RefreshDatabase;

    private function report(array $attributes = []): OcrFailedDocument
    {
        return OcrFailedDocument::create(array_merge([
            'station_id' => Station::factory()->create()->id,
            'ocr_blocks' => [['text' => 'X']],
            'status' => 'pending',
        ], $attributes));
    }

    public function test_old_pending_rows_lose_their_pii_but_keep_the_blocks(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('ocr-failed/sample.jpg', 'bytes');

        $row = $this->report([
            'ocr_text' => 'JUAN PEREZ',
            'image_path' => 'ocr-failed/sample.jpg',
        ]);
        $row->forceFill(['created_at' => now()->subDays(20)])->save();

        $this->artisan('ocr:purge-failed-documents')->assertSuccessful();

        $row->refresh();
        $this->assertNull($row->ocr_text);
        $this->assertNull($row->image_path);
        $this->assertNotNull($row->ocr_blocks);
        Storage::disk('local')->assertMissing('ocr-failed/sample.jpg');
    }

    public function test_resolved_rows_are_deleted_after_the_short_window(): void
    {
        $row = $this->report(['status' => 'converted']);
        $row->forceFill(['created_at' => now()->subDays(40)])->save();

        $this->artisan('ocr:purge-failed-documents')->assertSuccessful();

        $this->assertDatabaseMissing('ocr_failed_documents', ['id' => $row->id]);
    }

    public function test_pending_rows_are_deleted_after_the_full_window(): void
    {
        $row = $this->report();
        $row->forceFill(['created_at' => now()->subDays(120)])->save();

        $this->artisan('ocr:purge-failed-documents')->assertSuccessful();

        $this->assertDatabaseMissing('ocr_failed_documents', ['id' => $row->id]);
    }

    public function test_recent_rows_are_left_untouched(): void
    {
        $row = $this->report(['ocr_text' => 'JUAN PEREZ']);

        $this->artisan('ocr:purge-failed-documents')->assertSuccessful();

        $row->refresh();
        $this->assertSame('JUAN PEREZ', $row->ocr_text);
    }

    public function test_dry_run_changes_nothing(): void
    {
        $row = $this->report(['status' => 'discarded']);
        $row->forceFill(['created_at' => now()->subDays(40)])->save();

        $this->artisan('ocr:purge-failed-documents', ['--dry-run' => true])->assertSuccessful();

        $this->assertDatabaseHas('ocr_failed_documents', ['id' => $row->id]);
    }
}
