<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\Goat;
use App\Support\SurveyOptions;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public function farms(Collection $farms, string $format)
    {
        $headers = [
            'farm_code',
            'farm_uuid',
            'ward_number',
            'total_goats',
            'sampled_goats',
            'feeding_system',
            'feeding_system_label',
            'deworming_frequency',
            'deworming_frequency_label',
            'common_anthelmintic',
            'common_anthelmintic_label',
            'other_anthelmintic',
            'schedule_decision_maker',
            'schedule_decision_maker_label',
            'other_decision_maker',
            'dose_determination_method',
            'dose_determination_method_label',
            'created_at',
            'survey_date_npt',
            'updated_at',
        ];

        $rows = $farms->map(function (Farm $farm) {
            return [
                $farm->farm_code,
                $farm->uuid,
                $farm->ward_number,
                $farm->total_goats,
                $farm->goats_count ?? $farm->goats()->count(),
                $farm->feeding_system,
                SurveyOptions::label(SurveyOptions::FEEDING, $farm->feeding_system),
                $farm->deworming_frequency,
                SurveyOptions::label(SurveyOptions::FARM_DEWORMING_FREQUENCY, $farm->deworming_frequency),
                $farm->common_anthelmintic,
                SurveyOptions::label(SurveyOptions::FARM_ANTHELMINTIC, $farm->common_anthelmintic),
                $farm->other_anthelmintic,
                $farm->schedule_decision_maker,
                SurveyOptions::label(SurveyOptions::DECISION_MAKER, $farm->schedule_decision_maker),
                $farm->other_decision_maker,
                $farm->dose_determination_method,
                SurveyOptions::label(SurveyOptions::DOSE_METHOD, $farm->dose_determination_method),
                optional($farm->created_at)->toIso8601String(),
                optional($farm->created_at)?->timezone('Asia/Kathmandu')->toDateString(),
                optional($farm->updated_at)->toIso8601String(),
            ];
        })->all();

        return $this->download('farms', $headers, $rows, $format);
    }

    public function goats(Collection $goats, string $format)
    {
        $headers = [
            'goat_code',
            'goat_uuid',
            'farm_code',
            'farm_uuid',
            'ward_number',
            'age_value',
            'age_unit',
            'sex',
            'breed',
            'breed_label',
            'other_breed',
            'body_condition_score',
            'last_deworming_duration',
            'last_deworming_duration_label',
            'last_deworming_medicine',
            'last_deworming_medicine_label',
            'other_last_deworming_medicine',
            'deworming_frequency_last_12_months',
            'deworming_frequency_last_12_months_label',
            'farm_total_goats',
            'farm_feeding_system',
            'farm_feeding_system_label',
            'farm_deworming_frequency',
            'farm_deworming_frequency_label',
            'farm_common_anthelmintic',
            'created_at',
            'survey_date_npt',
            'updated_at',
        ];

        $rows = $goats->map(function (Goat $goat) {
            $farm = $goat->farm;

            return [
                $goat->goat_code,
                $goat->uuid,
                $farm?->farm_code,
                $goat->farm_uuid,
                $farm?->ward_number,
                $goat->age_value,
                $goat->age_unit,
                $goat->sex,
                $goat->breed,
                SurveyOptions::label(SurveyOptions::BREED, $goat->breed),
                $goat->other_breed,
                $goat->body_condition_score,
                $goat->last_deworming_duration,
                SurveyOptions::label(SurveyOptions::LAST_DEWORMING, $goat->last_deworming_duration),
                $goat->last_deworming_medicine,
                SurveyOptions::label(SurveyOptions::GOAT_MEDICINE, $goat->last_deworming_medicine),
                $goat->other_last_deworming_medicine,
                $goat->deworming_frequency_last_12_months,
                SurveyOptions::label(SurveyOptions::GOAT_FREQUENCY, $goat->deworming_frequency_last_12_months),
                $farm?->total_goats,
                $farm?->feeding_system,
                SurveyOptions::label(SurveyOptions::FEEDING, $farm?->feeding_system),
                $farm?->deworming_frequency,
                SurveyOptions::label(SurveyOptions::FARM_DEWORMING_FREQUENCY, $farm?->deworming_frequency),
                $farm?->common_anthelmintic,
                optional($goat->created_at)->toIso8601String(),
                optional($goat->created_at)?->timezone('Asia/Kathmandu')->toDateString(),
                optional($goat->updated_at)->toIso8601String(),
            ];
        })->all();

        return $this->download('goats', $headers, $rows, $format);
    }

    private function download(string $name, array $headers, array $rows, string $format)
    {
        $stamp = now()->timezone('Asia/Kathmandu')->format('Y-m-d');
        $filename = "sundarbazar-{$name}-{$stamp}.{$format}";

        if ($format === 'csv') {
            return response()->streamDownload(function () use ($headers, $rows) {
                $out = fopen('php://output', 'w');
                fwrite($out, "\xEF\xBB\xBF");
                fputcsv($out, $headers);
                foreach ($rows as $row) {
                    fputcsv($out, $row);
                }
                fclose($out);
            }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
        }

        $response = new StreamedResponse(function () use ($headers, $rows, $name) {
            $sheet = new Spreadsheet;
            $active = $sheet->getActiveSheet();
            $active->setTitle(ucfirst($name));
            $active->fromArray($headers, null, 'A1');
            if ($rows !== []) {
                $active->fromArray($rows, null, 'A2');
            }
            $active->getStyle('1:1')->getFont()->setBold(true);
            $writer = new Xlsx($sheet);
            $writer->save('php://output');
            $sheet->disconnectWorksheets();
        });
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'"');

        return $response;
    }
}
