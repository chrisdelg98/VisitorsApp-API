<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreVisitorRequest;
use App\Http\Requests\UpdateVisitorRequest;
use App\Http\Resources\VisitorResource;
use App\Http\Resources\VisitResource;
use App\Models\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VisitorController extends Controller
{
    /**
     * Search visitors by name, document, email or phone (substring match).
     * GET /v1/visitors/search?q=...
     */
    public function search(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        if ($q === '' || mb_strlen($q) < 2) {
            return response()->json([
                'success' => true,
                'data'    => [],
                'message' => 'Provide at least 2 characters to search.',
            ]);
        }

        $like = '%'.$q.'%';

        $visitors = Visitor::query()
            ->where(function ($query) use ($like) {
                $query->where('first_name', 'like', $like)
                    ->orWhere('last_name', 'like', $like)
                    ->orWhere('document_number', 'like', $like)
                    ->orWhere('email', 'like', $like)
                    ->orWhere('phone', 'like', $like);
            })
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'data'    => VisitorResource::collection($visitors),
        ]);
    }

    /**
     * GET /v1/visitors/{visitor}/latest-visit
     */
    public function latestVisit(Visitor $visitor): JsonResponse
    {
        $visit = $visitor->visits()->orderByDesc('check_in')->first();

        if (! $visit) {
            return response()->json([
                'success' => false,
                'message' => 'This visitor has no recorded visits yet.',
                'code'    => 'NO_VISITS',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data'    => new VisitResource($visit),
        ]);
    }

    /**
     * POST /v1/visitors
     */
    public function store(StoreVisitorRequest $request): JsonResponse
    {
        $visitor = Visitor::create($request->validated());

        return response()->json([
            'success' => true,
            'data'    => new VisitorResource($visitor),
            'message' => 'Visitor created successfully.',
        ], 201);
    }

    /**
     * PUT /v1/visitors/{visitor}
     */
    public function update(UpdateVisitorRequest $request, Visitor $visitor): JsonResponse
    {
        $visitor->update($request->validated());

        return response()->json([
            'success' => true,
            'data'    => new VisitorResource($visitor),
            'message' => 'Visitor updated successfully.',
        ]);
    }
}
