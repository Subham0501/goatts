<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Goat;
use App\Services\SyncService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    public function push(Request $request, SyncService $sync)
    {
        $request->validate([
            'farms' => ['sometimes', 'array', 'max:200'],
            'goats' => ['sometimes', 'array', 'max:500'],
        ]);

        $results = $sync->syncBatch(
            $request->input('farms', []),
            $request->input('goats', []),
            $request->user()
        );

        return response()->json([
            'results' => $results,
            'server_time' => now()->toIso8601String(),
        ]);
    }

    public function pull(Request $request, SyncService $sync)
    {
        $serverTime = now();
        $since = $request->query('since');
        $sinceAt = null;

        if (is_string($since) && $since !== '') {
            try {
                $sinceAt = Carbon::parse($since);
            } catch (\Throwable) {
                return response()->json(['message' => 'The since value is not a valid date.'], 422);
            }
        }

        $farms = Farm::withTrashed()
            ->withCount('goats')
            ->when($sinceAt, fn ($query) => $query->where('updated_at', '>', $sinceAt))
            ->orderBy('id')
            ->limit(2000)
            ->get();

        $goats = Goat::withTrashed()
            ->with(['farm' => fn ($query) => $query->withTrashed()])
            ->when($sinceAt, fn ($query) => $query->where('updated_at', '>', $sinceAt))
            ->orderBy('id')
            ->limit(10000)
            ->get();

        return response()->json([
            'server_time' => $serverTime->toIso8601String(),
            'farms' => $farms->map(fn (Farm $farm) => $sync->presentFarm($farm))->values(),
            'goats' => $goats->map(fn (Goat $goat) => $sync->presentGoat($goat))->values(),
        ]);
    }

    public function summary()
    {
        return response()->json([
            'farms' => Farm::query()->count(),
            'goats' => Goat::query()->count(),
            'server_time' => now()->toIso8601String(),
        ]);
    }
}
