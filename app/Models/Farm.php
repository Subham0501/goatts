<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Farm extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid',
        'farm_code',
        'ward_number',
        'total_goats',
        'feeding_system',
        'deworming_frequency',
        'common_anthelmintic',
        'other_anthelmintic',
        'schedule_decision_maker',
        'other_decision_maker',
        'dose_determination_method',
        'created_by',
        'updated_by',
        'client_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'ward_number' => 'integer',
            'total_goats' => 'integer',
            'client_updated_at' => 'datetime',
        ];
    }

    public function goats(): HasMany
    {
        return $this->hasMany(Goat::class, 'farm_uuid', 'uuid');
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
        if ($request->filled('ward')) {
            $query->where('ward_number', (int) $request->input('ward'));
        }

        if ($request->filled('feeding_system')) {
            $query->where('feeding_system', $request->input('feeding_system'));
        }

        if ($request->filled('deworming_frequency')) {
            $query->where('deworming_frequency', $request->input('deworming_frequency'));
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where('farm_code', 'like', '%'.$search.'%');
        }

        return $query;
    }
}
