<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\DocumentTemplateResource;
use App\Models\DocumentTemplate;
use App\Models\DocumentType;
use App\Support\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class OcrTemplateController extends Controller
{
    /**
     * GET /v1/ocr/templates — full snapshot of the published OCR catalog.
     *
     * The device REPLACES its whole local copy with this response, so deletions
     * resolve by absence and a corrupted local catalog self-heals. There are no
     * deltas: the cheap daily check is the conditional GET below.
     *
     * ETag == catalog_version. Same value from the client → 304, no body.
     */
    public function index(Request $request): JsonResponse|Response
    {
        $catalogVersion = $this->catalogVersion();
        $etag           = (string) $catalogVersion;

        if ($this->clientHasVersion($request, $etag)) {
            return response()->noContent(304)->withHeaders($this->cacheHeaders($etag));
        }

        // Templates of a deactivated type are withheld: switching is_active off
        // is how the portal pulls a whole document type from the fleet.
        $templates = DocumentTemplate::query()
            ->published()
            ->whereHas('documentType', fn ($q) => $q->where('is_active', true))
            ->with('documentType')
            ->orderBy('document_type_id')
            ->orderBy('version')
            ->orderBy('side')
            ->get();

        $station = $request->attributes->get('station');

        AuditLogger::log('tablet.ocr.catalog_synced', $request, [
            'station_id'      => $station ? (string) $station->id : null,
            'station_code'    => $station ? (string) $station->code : null,
            'catalog_version' => $catalogVersion,
            'count'           => $templates->count(),
        ]);

        return response()->json([
            'success'         => true,
            'data'            => DocumentTemplateResource::collection($templates),
            'catalog_version' => $catalogVersion,
            'count'           => $templates->count(),
        ], 200, $this->cacheHeaders($etag));
    }

    /**
     * Highest updated_at across the catalog tables, as a UNIX timestamp.
     *
     * Deliberately computed over ALL rows of both tables instead of only the
     * rows served: a template moving published → deprecated (or a type being
     * deactivated) must also bump the version, and those rows disappear from
     * the served set at the same instant their updated_at changes.
     *
     * Computed in PHP rather than with UNIX_TIMESTAMP() so it behaves the same
     * on MySQL and on the SQLite database used by the test suite.
     */
    private function catalogVersion(): int
    {
        $stamps = [
            DocumentTemplate::max('updated_at'),
            DocumentType::max('updated_at'),
        ];

        $timestamps = array_map(
            fn ($value) => Carbon::parse($value)->getTimestamp(),
            array_filter($stamps)
        );

        return $timestamps === [] ? 0 : max($timestamps);
    }

    /**
     * True when If-None-Match matches the current version. Tolerates the weak
     * validator prefix and the quoting some HTTP clients add.
     */
    private function clientHasVersion(Request $request, string $etag): bool
    {
        $header = $request->header('If-None-Match');

        if ($header === null || $header === '') {
            return false;
        }

        foreach (explode(',', $header) as $candidate) {
            $candidate = trim($candidate);
            $candidate = preg_replace('/^W\//', '', $candidate) ?? $candidate;
            $candidate = trim($candidate, '"');

            if ($candidate === $etag || $candidate === '*') {
                return true;
            }
        }

        return false;
    }

    /** @return array<string, string> */
    private function cacheHeaders(string $etag): array
    {
        return [
            'ETag'          => '"'.$etag.'"',
            // Always revalidate: the catalog must never be served stale from a proxy.
            'Cache-Control' => 'private, no-cache',
        ];
    }
}
