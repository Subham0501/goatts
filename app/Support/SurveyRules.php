<?php

namespace App\Support;

class SurveyRules
{
    public static function farm(): array
    {
        $min = (int) config('survey.ward_min');
        $max = (int) config('survey.ward_max');

        return [
            'uuid' => ['required', 'uuid'],
            'farm_code' => ['required', 'string', 'max:64', 'regex:/^[\pL\pN][\pL\pN._ -]{0,62}$/u'],
            'ward_number' => ['required', 'integer', 'between:'.$min.','.$max],
            'total_goats' => ['required', 'integer', 'min:1', 'max:100000'],
            'feeding_system' => ['required', 'in:'.SurveyOptions::keys(SurveyOptions::FEEDING)],
            'deworming_frequency' => ['required', 'in:'.SurveyOptions::keys(SurveyOptions::FARM_DEWORMING_FREQUENCY)],
            'common_anthelmintic' => ['required', 'in:'.SurveyOptions::keys(SurveyOptions::FARM_ANTHELMINTIC)],
            'other_anthelmintic' => ['nullable', 'string', 'max:255', 'required_if:common_anthelmintic,other'],
            'schedule_decision_maker' => ['required', 'in:'.SurveyOptions::keys(SurveyOptions::DECISION_MAKER)],
            'other_decision_maker' => ['nullable', 'string', 'max:255', 'required_if:schedule_decision_maker,other'],
            'dose_determination_method' => ['required', 'in:'.SurveyOptions::keys(SurveyOptions::DOSE_METHOD)],
            'created_at' => ['required', 'date'],
            'updated_at' => ['required', 'date'],
            'deleted_at' => ['nullable', 'date'],
            'base_updated_at' => ['nullable', 'date'],
            'resolution' => ['nullable', 'in:client,server'],
        ];
    }

    public static function goat(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            'farm_uuid' => ['required', 'uuid'],
            'goat_code' => ['required', 'string', 'max:64', 'regex:/^[\pL\pN][\pL\pN._ -]{0,62}$/u'],
            'age_value' => ['required', 'integer', 'min:0', 'max:360'],
            'age_unit' => ['required', 'in:'.SurveyOptions::keys(SurveyOptions::AGE_UNIT)],
            'sex' => ['required', 'in:'.SurveyOptions::keys(SurveyOptions::SEX)],
            'breed' => ['required', 'in:'.SurveyOptions::keys(SurveyOptions::BREED)],
            'other_breed' => ['nullable', 'string', 'max:255', 'required_if:breed,other'],
            'body_condition_score' => ['required', 'integer', 'between:1,5'],
            'last_deworming_duration' => ['required', 'in:'.SurveyOptions::keys(SurveyOptions::LAST_DEWORMING)],
            'last_deworming_medicine' => ['required', 'in:'.SurveyOptions::keys(SurveyOptions::GOAT_MEDICINE)],
            'other_last_deworming_medicine' => ['nullable', 'string', 'max:255', 'required_if:last_deworming_medicine,other'],
            'deworming_frequency_last_12_months' => ['required', 'in:'.SurveyOptions::keys(SurveyOptions::GOAT_FREQUENCY)],
            'created_at' => ['required', 'date'],
            'updated_at' => ['required', 'date'],
            'deleted_at' => ['nullable', 'date'],
            'base_updated_at' => ['nullable', 'date'],
            'resolution' => ['nullable', 'in:client,server'],
        ];
    }

    public static function deletion(): array
    {
        return [
            'uuid' => ['required', 'uuid'],
            'deleted_at' => ['required', 'date'],
            'updated_at' => ['nullable', 'date'],
            'base_updated_at' => ['nullable', 'date'],
            'resolution' => ['nullable', 'in:client,server'],
        ];
    }
}
