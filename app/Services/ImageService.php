<?php

namespace App\Services;

use App\Models\Visit;
use App\Models\VisitImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    /**
     * Store an uploaded image for a visit. Replaces any existing image
     * of the same type (the visit_id+type pair is unique in DB).
     */
    public function storeForVisit(Visit $visit, string $type, UploadedFile $file): VisitImage
    {
        $hash      = hash_file('sha256', $file->getRealPath());
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $filename  = Str::uuid()->toString().'.'.$extension;
        $directory = 'visitors/'.$visit->id;
        $path      = $file->storeAs($directory, $filename, 'local');

        $existing = VisitImage::where('visit_id', $visit->id)
            ->where('type', $type)
            ->first();

        if ($existing) {
            Storage::disk('local')->delete($existing->file_path);
            $existing->update([
                'file_path' => $path,
                'file_hash' => $hash,
            ]);

            return $existing;
        }

        return VisitImage::create([
            'visit_id'  => $visit->id,
            'type'      => $type,
            'file_path' => $path,
            'file_hash' => $hash,
        ]);
    }

    /**
     * Store the sample image of an unreadable document on the private disk and
     * return its path. Same disk and layout as visit images: never public, only
     * readable through an authenticated endpoint.
     */
    public function storeOcrSample(string $failedDocumentId, UploadedFile $file): string
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension());
        $filename  = Str::uuid()->toString().'.'.$extension;

        return $file->storeAs('ocr-failed/'.$failedDocumentId, $filename, 'local');
    }
}
