<?php

return [

    /*
    |--------------------------------------------------------------------------
    | OCR failed-document retention
    |--------------------------------------------------------------------------
    |
    | Rows in `ocr_failed_documents` hold personal data (OCR text and, when the
    | device could not produce usable blocks, a photo of an ID document). They
    | exist only long enough to be turned into a template, so they are purged
    | on a schedule by `ocr:purge-failed-documents` (see routes/console.php).
    |
    | `resolved_retention_days` covers items already dealt with (converted or
    | discarded) and is intentionally shorter than the open-queue retention.
    |
    | `text_retention_days` strips the PII (ocr_text and the sample image) from
    | rows that must stay in the queue longer, keeping only the structured
    | ocr_blocks needed to build the template.
    |
    */

    'retention_days' => (int) env('OCR_FAILED_RETENTION_DAYS', 90),

    'resolved_retention_days' => (int) env('OCR_FAILED_RESOLVED_RETENTION_DAYS', 30),

    'text_retention_days' => (int) env('OCR_FAILED_TEXT_RETENTION_DAYS', 15),

    /*
    |--------------------------------------------------------------------------
    | Front-side acceptance band
    |--------------------------------------------------------------------------
    |
    | A report is only queued when the FRONT classifier confidence falls inside
    | [min, max). The back side has no templates yet, so it always scores low and
    | is never used to gate acceptance. Reports outside the band get a 200
    | response with `stored: false` (no row is written, so the device does not
    | retry) and never reach the review queue.
    |
    | `min` trims the flood of unusable low-confidence noise.
    |
    | `max` is the other end: above it the app essentially recognized the
    | document, so the report says more about extraction than about a missing
    | template and only buries the real work in the queue. The default 0.40
    | means "39% and below gets reviewed"; the bound itself is excluded.
    |
    | Set OCR_MAX_FRONT_CONFIDENCE=1 to disable the upper bound entirely.
    |
    */

    'min_front_confidence' => (float) env('OCR_MIN_FRONT_CONFIDENCE', 0.20),

    'max_front_confidence' => (float) env('OCR_MAX_FRONT_CONFIDENCE', 0.40),

];
