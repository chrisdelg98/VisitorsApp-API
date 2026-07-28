<?php

namespace App\Support;

/**
 * Structural check for Android packages.
 *
 * An APK is a ZIP whose root contains AndroidManifest.xml. The MIME type a
 * client reports for an .apk is not trustworthy, so this looks at the bytes.
 *
 * It also catches the classic FTP/SFTP failure: a transfer that was cut halfway
 * leaves a file that lists fine but has no ZIP central directory at the end, so
 * opening it fails here instead of shipping a broken build to the fleet.
 */
class ApkFile
{
    public static function isValid(?string $path): bool
    {
        if ($path === null || $path === '' || ! is_file($path)) {
            return false;
        }

        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $magic = (string) fread($handle, 4);
        fclose($handle);

        if ($magic !== "PK\x03\x04") {
            return false;
        }

        if (! class_exists(\ZipArchive::class)) {
            return true;
        }

        $zip = new \ZipArchive();

        if ($zip->open($path) !== true) {
            return false;
        }

        $hasManifest = $zip->locateName('AndroidManifest.xml') !== false;
        $zip->close();

        return $hasManifest;
    }
}
