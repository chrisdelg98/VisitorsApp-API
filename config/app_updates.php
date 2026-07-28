<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Platform served by GET /v1/app/latest
    |--------------------------------------------------------------------------
    |
    | Devices may pass ?platform=, but the tablet fleet is Android-only, so the
    | request does not have to say anything.
    |
    */

    'default_platform' => env('APP_UPDATE_PLATFORM', 'android'),

    /*
    |--------------------------------------------------------------------------
    | Disk holding the APK binaries
    |--------------------------------------------------------------------------
    |
    | `local` is the PRIVATE disk (storage/app/private) — the same one used for
    | visit images. The binaries must never be reachable without an API key, so
    | do not point this at the `public` disk.
    |
    */

    'disk' => env('APP_UPDATE_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Maximum APK size accepted on upload (kilobytes)
    |--------------------------------------------------------------------------
    |
    | Current builds are ~150 MB. This is only the application-level cap: PHP
    | (`upload_max_filesize`, `post_max_size`, `max_input_time`) and the web
    | server (`client_max_body_size` on nginx) must allow at least as much or
    | the request never reaches Laravel.
    |
    */

    'max_apk_size_kb' => (int) env('APP_UPDATE_MAX_APK_KB', 204800),

];
