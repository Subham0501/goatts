<?php

namespace App\Support;

class SurveyOptions
{
    public const FEEDING = [
        'stall_feeding' => 'Stall feeding',
        'grazing' => 'Grazing',
        'semi_intensive' => 'Semi-intensive',
    ];

    public const FARM_DEWORMING_FREQUENCY = [
        'monthly' => 'Monthly',
        'every_2_3_months' => 'Every 2–3 months',
        'every_4_6_months' => 'Every 4–6 months',
        'once_a_year' => 'Once a year',
        'only_when_necessary' => 'Only when necessary',
        'no_fixed_schedule' => 'No fixed schedule',
    ];

    public const FARM_ANTHELMINTIC = [
        'albendazole' => 'Albendazole',
        'fenbendazole' => 'Fenbendazole',
        'ivermectin' => 'Ivermectin',
        'levamisole' => 'Levamisole',
        'other' => 'Other',
        'dont_know' => "Don't know",
    ];

    public const DECISION_MAKER = [
        'farmer' => 'Farmer',
        'veterinarian' => 'Veterinarian/livestock technician',
        'agro_vet' => 'Agro-vet',
        'other' => 'Other',
    ];

    public const DOSE_METHOD = [
        'actual_body_weight' => 'According to actual body weight',
        'estimated_body_weight' => 'According to estimated body weight',
        'according_to_age' => 'According to age',
        'previous_experience' => 'Based on previous experience',
        'professional_advice' => 'Professional advice',
    ];

    public const AGE_UNIT = [
        'months' => 'Months',
        'years' => 'Years',
    ];

    public const SEX = [
        'male' => 'Male',
        'female' => 'Female',
    ];

    public const BREED = [
        'local' => 'Local',
        'crossbred' => 'Crossbred',
        'other' => 'Other',
    ];

    public const LAST_DEWORMING = [
        'within_1_month' => 'Within 1 month',
        '1_3_months' => '1–3 months',
        '3_6_months' => '3–6 months',
        'more_than_6_months' => 'More than 6 months',
        'never' => 'Never dewormed',
        'unknown' => 'Unknown',
    ];

    public const GOAT_MEDICINE = [
        'albendazole' => 'Albendazole',
        'fenbendazole' => 'Fenbendazole',
        'ivermectin' => 'Ivermectin',
        'levamisole' => 'Levamisole',
        'other' => 'Other',
        'unknown' => 'Unknown',
    ];

    public const GOAT_FREQUENCY = [
        'once' => 'Once',
        'twice' => 'Twice',
        'three_times' => 'Three times',
        'more_than_three' => 'More than three times',
        'never' => 'Never',
        'unknown' => 'Unknown',
    ];

    public static function keys(array $options): string
    {
        return implode(',', array_keys($options));
    }

    public static function label(array $options, ?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        return $options[$value] ?? $value;
    }
}
