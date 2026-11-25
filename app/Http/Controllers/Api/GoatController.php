<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Goat;
use App\Services\SyncService;
use Illuminate\Http\Request;

class GoatController extends Controller
{
    public function index(Request $request, SyncService $sync)
    {
        $goats = Goat::query()
            ->with('farm')
            ->surveyFilters($request)
            ->latest('id')
            ->paginate(50)
            ->withQueryString();

        return $goats->through(fn (Goat $goat) => $sync->presentGoat($goat));
    }

    public function store(Request $request, SyncService $sync)
    {
        return $this->respond($sync->syncGoat($request->all(), $request->user()), 201);
    }

    public function show(string $uuid, SyncService $sync)
    {
        $goat = Goat::query()->with('farm')->where('uuid', $uuid)->firstOrFail();

        return response()->json($sync->presentGoat($goat));
    }

    public function update(Request $request, string $uuid, SyncService $sync)
    {
        $payload = array_merge($request->all(), ['uuid' => $uuid]);

        return $this->respond($sync->syncGoat($payload, $request->user()));
    }

    public function destroy(Request $request, string $uuid, SyncService $sync)
    {
        $payload = array_merge($request->all(), [
            'uuid' => $uuid,
            'deleted_at' => $request->input('deleted_at', now()->toIso8601String()),
        ]);

        return $this->respond($sync->syncGoat($payload, $request->user()));
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
