<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Review queue: documents a tablet could not read. The portal turns these into
 * templates. Rows carry PII (ocr_text, sample image) and are purged on a
 * schedule — see App\Console\Commands\PurgeOcrFailedDocuments and config/ocr.php.
 */
class OcrFailedDocument extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'station_id',
        'detected_type',
        'detected_confidence',
        'match_score',
        'ocr_blocks',
        'ocr_text',
        'extracted_fields',
        'image_path',
        'image_back_path',
        'app_version',
        'status',
        'matched_template_id',
        'reviewed_by',
    ];

    protected $casts = [
        'ocr_blocks'          => 'array',
        'extracted_fields'    => 'array',
        'detected_confidence' => 'decimal:3',
        'match_score'         => 'decimal:3',
    ];

    /**
     * Never leak PII or raw storage paths through a JSON response — the image
     * paths, the OCR text and the app's structured reading (which holds the
     * actual values read off the document) are only for the portal, server-side.
     */
    protected $hidden = [
        'image_path',
        'image_back_path',
        'ocr_text',
        'extracted_fields',
    ];

    public function station(): BelongsTo
    {
        return $this->belongsTo(Station::class);
    }

    public function matchedTemplate(): BelongsTo
    {
        return $this->belongsTo(DocumentTemplate::class, 'matched_template_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
