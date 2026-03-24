<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Visitors App v1
|--------------------------------------------------------------------------
| All routes are prefixed with /api (configured in bootstrap/app.php).
| V1 routes will be registered here grouped under /api/v1.
*/

Route::get('/health', fn () => response()->json(['status' => 'ok']));
