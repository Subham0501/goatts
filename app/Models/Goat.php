<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Goat extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'farm_uuid',
        'goat_code',
        'age_value',
        'age_unit',
        'sex',
        'breed',
        'other_breed',
        'body_condition_score',
        'last_deworming_duration',
        'last_deworming_medicine',
        'other_last_deworming_medicine',
        'deworming_frequency_last_12_months',
        'created_by',
        'updated_by',
        'client_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'age_value' => 'integer',
            'body_condition_score' => 'integer',
            'client_updated_at' => 'datetime',
        ];
    }

    public function farm(): BelongsTo
    {
        return $this->belongsTo(Farm::class, 'farm_uuid', 'uuid');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeSurveyFilters($query, $request)
    {
        if ($request->filled('sex')) {
            $query->where('sex', $request->input('sex'));
        }

        if ($request->filled('breed')) {
            $query->where('breed', $request->input('breed'));
        }

        if ($request->filled('farm_uuid')) {
            $query->where('farm_uuid', $request->input('farm_uuid'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($inner) use ($search) {
                $inner->where('goat_code', 'like', '%'.$search.'%')
                    ->orWhere('farm_uuid', 'like', '%'.$search.'%')
                    ->orWhereHas('farm', function ($farm) use ($search) {
                        $farm->where('farm_code', 'like', '%'.$search.'%');
                    });
            });
        }

        if ($request->filled('ward') || $request->filled('feeding_system') || $request->filled('deworming_frequency')) {
            $query->whereHas('farm', function ($farm) use ($request) {
                if ($request->filled('ward')) {
                    $farm->where('ward_number', (int) $request->input('ward'));
                }
                if ($request->filled('feeding_system')) {
                    $farm->where('feeding_system', $request->input('feeding_system'));
                }
                if ($request->filled('deworming_frequency')) {
                    $farm->where('deworming_frequency', $request->input('deworming_frequency'));
                }
            });
        }

        return $query;
    }
}
