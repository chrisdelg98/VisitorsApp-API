<?php

namespace App\Console\Commands;

use App\Models\OcrFailedDocument;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Data-retention job for the OCR review queue.
 *
 * Three passes, from least to most destructive:
 *   1. minimize — drop ocr_text, the app's extracted_fields and the sample
 *      image from rows still in the queue past `text_retention_days`, keeping
 *      the structured ocr_blocks.
 *   2. resolved — delete converted/discarded rows past `resolved_retention_days`.
 *   3. expired  — delete anything past `retention_days`, reviewed or not.
 */
class PurgeOcrFailedDocuments extends Command
{
    protected $signature = 'ocr:purge-failed-documents {--dry-run : Report what would be purged without writing}';

    protected $description = 'Apply the retention policy to the OCR failed-document review queue';

    public function handle(): int
    {
        $dryRun   = (bool) $this->option('dry-run');
        $minimized = $this->minimizeOldPii($dryRun);
        $resolved  = $this->deleteOlderThan(
            (int) config('ocr.resolved_retention_days'),
            ['converted', 'discarded'],
            $dryRun
        );
        $expired   = $this->deleteOlderThan((int) config('ocr.retention_days'), null, $dryRun);

        $summary = [
            'minimized' => $minimized,
            'resolved'  => $resolved,
            'expired'   => $expired,
            'dry_run'   => $dryRun,
        ];

        $this->info(sprintf(
            'OCR retention: %d minimized, %d resolved deleted, %d expired deleted%s.',
            $minimized,
            $resolved,
            $expired,
            $dryRun ? ' (dry run)' : ''
        ));

        if (! $dryRun && ($minimized || $resolved || $expired)) {
            Log::channel('audit')->info('system.ocr.retention_purge', $summary);
        }

        return self::SUCCESS;
    }

    /** Strip PII from rows still awaiting review past the text retention window. */
    private function minimizeOldPii(bool $dryRun): int
    {
        $cutoff = now()->subDays((int) config('ocr.text_retention_days'));

        $query = OcrFailedDocument::query()
            ->whereIn('status', ['pending', 'in_review'])
            ->where('created_at', '<', $cutoff)
            ->where(function ($q) {
                $q->whereNotNull('ocr_text')
                    ->orWhereNotNull('extracted_fields')
                    ->orWhereNotNull('image_path')
                    ->orWhereNotNull('image_back_path');
            });

        if ($dryRun) {
            return $query->count();
        }

        $count = 0;

        $query->chunkById(200, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $this->deleteImage($row);
                $row->update([
                    'ocr_text'         => null,
                    'extracted_fields' => null,
                    'image_path'       => null,
                    'image_back_path'  => null,
                ]);
                $count++;
            }
        });

        return $count;
    }

    /**
     * @param  array<int, string>|null  $statuses  null = any status
     */
    private function deleteOlderThan(int $days, ?array $statuses, bool $dryRun): int
    {
        $query = OcrFailedDocument::query()->where('created_at', '<', now()->subDays($days));

        if ($statuses !== null) {
            $query->whereIn('status', $statuses);
        }

        if ($dryRun) {
            return $query->count();
        }

        $count = 0;

        $query->chunkById(200, function ($rows) use (&$count) {
            foreach ($rows as $row) {
                $this->deleteImage($row);
                $row->delete();
                $count++;
            }
        });

        return $count;
    }

    private function deleteImage(OcrFailedDocument $row): void
    {
        foreach ([$row->image_path, $row->image_back_path] as $path) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }
        }
    }
}
