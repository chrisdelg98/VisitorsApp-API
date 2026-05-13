<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateVisitStatusRequest;
use App\Http\Resources\VisitResource;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitController extends Controller
{
    /**
     * GET /v1/admin/visits — paginated, filterable list of all visits.
     * Query params: station_id, status, visitor_id, from, to, q (search visiting_person),
     *               per_page (default 25, max 100), page.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 25);
        $perPage = max(1, min($perPage, 100));

        $query = Visit::query()->with(['visitor', 'station']);

        if ($request->filled('station_id')) {
            $query->where('station_id', $request->string('station_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }

        if ($request->filled('visitor_id')) {
            $query->where('visitor_id', $request->string('visitor_id'));
        }

        if ($request->filled('from')) {
            $query->where('check_in', '>=', $request->date('from'));
        }

        if ($request->filled('to')) {
            $query->where('check_in', '<=', $request->date('to'));
        }

        if ($request->filled('q')) {
            $q = '%' . $request->string('q') . '%';
            $query->where('visiting_person', 'like', $q);
        }

        $paginator = $query->orderByDesc('check_in')->paginate($perPage);

        return response()->json([
            'success' => true,
            'data'    => VisitResource::collection($paginator->items()),
            'meta'    => [
                'current_page' => $paginator->currentPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'last_page'    => $paginator->lastPage(),
            ],
        ]);
    }

    /**
     * GET /v1/admin/visits/{visit} — full detail including images.
     */
    public function show(Visit $visit): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data'    => new VisitResource($visit->load(['visitor', 'station', 'images'])),
        ]);
    }

    /**
     * PATCH /v1/admin/visits/{visit}/status — force a status change.
     */
    public function updateStatus(UpdateVisitStatusRequest $request, Visit $visit): JsonResponse
    {
        $newStatus = $request->string('status')->value();

        $visit->status = $newStatus;
        if ($newStatus === 'completed' && $visit->check_out === null) {
            $visit->check_out = now();
        }
        if ($newStatus === 'active') {
            $visit->check_out = null;
        }
        $visit->save();

        return response()->json([
            'success' => true,
            'message' => 'Visit status updated successfully.',
            'data'    => new VisitResource($visit->load(['visitor', 'station'])),
        ]);
    }
}
