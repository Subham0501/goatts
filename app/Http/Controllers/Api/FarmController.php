<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Services\SyncService;
use Illuminate\Http\Request;

class FarmController extends Controller
{
    public function index(Request $request, SyncService $sync)
    {
        $farms = Farm::query()
            ->withCount('goats')
            ->surveyFilters($request)
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return $farms->through(fn (Farm $farm) => $sync->presentFarm($farm));
    }

    public function store(Request $request, SyncService $sync)
    {
        return $this->respond($sync->syncFarm($request->all(), $request->user()), 201);
    }

    public function show(string $uuid, SyncService $sync)
    {
        $farm = Farm::query()->withCount('goats')->where('uuid', $uuid)->firstOrFail();

        return response()->json($sync->presentFarm($farm));
    }

    public function update(Request $request, string $uuid, SyncService $sync)
    {
        $payload = array_merge($request->all(), ['uuid' => $uuid]);

        return $this->respond($sync->syncFarm($payload, $request->user()));
    }

    public function destroy(Request $request, string $uuid, SyncService $sync)
    {
        $payload = array_merge($request->all(), [
            'uuid' => $uuid,
            'deleted_at' => $request->input('deleted_at', now()->toIso8601String()),
        ]);

        return $this->respond($sync->syncFarm($payload, $request->user()));
    }

    public function goats(string $uuid, SyncService $sync)
    {
        $farm = Farm::query()->where('uuid', $uuid)->firstOrFail();
        $goats = $farm->goats()->with('farm')->orderBy('goat_code')->get();

        return response()->json($goats->map(fn ($goat) => $sync->presentGoat($goat))->values());
    }

    private function respond(array $result, int $success = 200)
    {
        $code = match ($result['status']) {
            'synced' => $success,
            'conflict' => 409,
            'blocked' => 409,
            default => 422,
        };

        return response()->json($result, $code);
    }
}
