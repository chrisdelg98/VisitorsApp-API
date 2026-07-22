<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Data retention for the OCR review queue (personal data — see config/ocr.php).
Schedule::command('ocr:purge-failed-documents')
    ->dailyAt('03:15')
    ->withoutOverlapping();
