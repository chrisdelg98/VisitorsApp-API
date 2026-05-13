<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Models\Station;
use App\Models\Visit;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StatsController extends Controller
{
    /**
     * GET /v1/admin/stats — aggregate stats by station and period.
     * Query params: from, to (ISO dates). Defaults to last 30 days.
     */
    public function index(Request $request): JsonResponse
    {
        $to   = $request->filled('to')   ? CarbonImmutable::parse($request->string('to'))   : CarbonImmutable::now();
        $from = $request->filled('from') ? CarbonImmutable::parse($request->string('from')) : $to->subDays(30);

        $base = Visit::query()
            ->whereBetween('check_in', [$from, $to]);

        $totals = [
            'total'     => (clone $base)->count(),
            'active'    => (clone $base)->where('status', 'active')->count(),
            'completed' => (clone $base)->where('status', 'completed')->count(),
        ];

        $byStation = (clone $base)
            ->select('station_id', DB::raw('COUNT(*) as visits'))
            ->groupBy('station_id')
            ->get()
            ->map(function ($row) {
                $station = Station::find($row->station_id);

                return [
                    'station_id'   => $row->station_id,
                    'station_name' => $station?->name,
                    'station_code' => $station?->code,
                    'visits'       => (int) $row->visits,
                ];
            });

        $byDay = (clone $base)
            ->select(DB::raw('DATE(check_in) as day'), DB::raw('COUNT(*) as visits'))
            ->groupBy('day')
            ->orderBy('day')
            ->get()
            ->map(fn ($row) => [
                'day'    => $row->day,
                'visits' => (int) $row->visits,
            ]);

        return response()->json([
            'success' => true,
            'data'    => [
                'period' => [
                    'from' => $from->toIso8601String(),
                    'to'   => $to->toIso8601String(),
                ],
                'totals'     => $totals,
                'by_station' => $byStation,
                'by_day'     => $byDay,
            ],
        ]);
    }
}
