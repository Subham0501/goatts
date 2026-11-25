<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Farm;
use App\Models\Goat;
use App\Services\ExportService;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function summary()
    {
        return response()->json([
            'farms' => Farm::query()->count(),
            'goats' => Goat::query()->count(),
            'farms_by_ward' => Farm::query()
                ->selectRaw('ward_number, count(*) as total')
                ->groupBy('ward_number')
                ->orderBy('ward_number')
                ->get(),
            'farms_by_feeding' => Farm::query()
                ->selectRaw('feeding_system, count(*) as total')
                ->groupBy('feeding_system')
                ->get(),
            'goats_by_sex' => Goat::query()
                ->selectRaw('sex, count(*) as total')
                ->groupBy('sex')
                ->get(),
            'goats_by_breed' => Goat::query()
                ->selectRaw('breed, count(*) as total')
                ->groupBy('breed')
                ->get(),
        ]);
    }

    public function farms(Request $request, ExportService $export)
    {
        $format = $this->format($request);
        $farms = Farm::query()
            ->withCount('goats')
            ->surveyFilters($request)
            ->orderBy('farm_code')
            ->limit(20000)
            ->get();

        return $export->farms($farms, $format);
    }

    public function goats(Request $request, ExportService $export)
    {
        $format = $this->format($request);
        $goats = Goat::query()
            ->with('farm')
            ->surveyFilters($request)
            ->orderBy('goat_code')
            ->limit(20000)
            ->get();

        return $export->goats($goats, $format);
    }

    private function format(Request $request): string
    {
        $format = (string) $request->query('format', 'xlsx');

        abort_unless(in_array($format, ['csv', 'xlsx'], true), 422, 'Choose csv or xlsx.');

        return $format;
    }
}
