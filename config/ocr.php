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

];
