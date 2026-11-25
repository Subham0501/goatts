<?php

namespace App\Services;

use App\Models\Farm;
use App\Models\Goat;
use App\Models\SyncLog;
use App\Models\User;
use App\Support\SurveyRules;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Throwable;

class SyncService
{
    public function syncBatch(array $farms, array $goats, User $user): array
    {
        $results = [];

        foreach ($farms as $farm) {
            $results[] = $this->syncFarm(is_array($farm) ? $farm : [], $user);
        }

        foreach ($goats as $goat) {
            $results[] = $this->syncGoat(is_array($goat) ? $goat : [], $user);
        }

        return $results;
    }

    public function syncFarm(array $payload, User $user): array
    {
        $payload = $this->blankToNull($payload);

        try {
            if (! empty($payload['deleted_at'])) {
                return $this->deleteFarm($payload, $user);
            }

            $validator = Validator::make($payload, SurveyRules::farm());
            if ($validator->fails()) {
                return $this->rejected('farm', $payload['uuid'] ?? null, $validator->errors()->first());
            }

            $data = $this->normalizeFarm($validator->validated());

            return DB::transaction(function () use ($data, $user) {
                $existing = Farm::withTrashed()->where('uuid', $data['uuid'])->lockForUpdate()->first();

                if (! $existing) {
                    if ($this->farmCodeTaken($data['farm_code'])) {
                        return $this->rejected('farm', $data['uuid'], 'Farm ID is already used by another farm. Edit the Farm ID and sync again.');
                    }

                    $farm = new Farm;
                    $this->writeFarm($farm, $data, $user, true);
                    $this->log($user, 'farm', $farm->uuid, 'created', 'Farm created from field sync.');

                    return $this->ok('farm', $farm, 'synced');
                }

                if ($this->sameFarm($existing, $data)) {
                    $this->log($user, 'farm', $existing->uuid, 'unchanged', 'Idempotent farm retry.');

                    return $this->ok('farm', $existing, 'synced');
                }

                if (($data['resolution'] ?? null) === 'server') {
                    $this->log($user, 'farm', $existing->uuid, 'conflict', 'Device accepted the server farm.');

                    return $this->ok('farm', $existing, 'synced', true);
                }

                if (($data['resolution'] ?? null) !== 'client' && $this->serverIsAhead($existing, $data['base_updated_at'] ?? null, $user)) {
                    $this->log($user, 'farm', $existing->uuid, 'conflict', 'Server farm is newer than the device copy.');

                    return $this->conflict('farm', $existing);
                }

                if ($this->farmCodeTaken($data['farm_code'], $existing->uuid)) {
                    return $this->rejected('farm', $data['uuid'], 'Farm ID is already used by another farm. Edit the Farm ID and sync again.');
                }

                $this->writeFarm($existing, $data, $user, false);
                $this->log($user, 'farm', $existing->uuid, 'updated', 'Farm updated from field sync.');

                return $this->ok('farm', $existing, 'synced');
            });
        } catch (Throwable $exception) {
            report($exception);

            return $this->rejected(
                'farm',
                $payload['uuid'] ?? null,
                config('app.debug') ? $exception->getMessage() : 'The server could not save this farm. It is still stored on this device.'
            );
        }
    }

    public function syncGoat(array $payload, User $user): array
    {
        $payload = $this->blankToNull($payload);

        try {
            if (! empty($payload['deleted_at'])) {
                return $this->deleteGoat($payload, $user);
            }

            $validator = Validator::make($payload, SurveyRules::goat());
            if ($validator->fails()) {
                return $this->rejected('goat', $payload['uuid'] ?? null, $validator->errors()->first());
            }

            $data = $this->normalizeGoat($validator->validated());

            if ($data['age_unit'] === 'years' && (int) $data['age_value'] > 30) {
                return $this->rejected('goat', $data['uuid'], 'Age in years cannot be more than 30. Record young goats in months.');
            }

            $farm = Farm::query()->where('uuid', $data['farm_uuid'])->first();
            if (! $farm) {
                return [
                    'entity' => 'goat',
                    'uuid' => $data['uuid'],
                    'status' => 'blocked',
                    'message' => 'The parent farm is not on the server yet. The goat stays on this device and will sync after the farm.',
                ];
            }

            return DB::transaction(function () use ($data, $user) {
                $existing = Goat::withTrashed()->where('uuid', $data['uuid'])->lockForUpdate()->first();

                if (! $existing) {
                    if ($this->goatCodeTaken($data['farm_uuid'], $data['goat_code'])) {
                        return $this->rejected('goat', $data['uuid'], 'Goat ID is already used on this farm. Edit the Goat ID and sync again.');
                    }

                    $goat = new Goat;
                    $this->writeGoat($goat, $data, $user, true);
                    $this->log($user, 'goat', $goat->uuid, 'created', 'Goat created from field sync.');

                    return $this->ok('goat', $goat, 'synced');
                }

                if ($this->sameGoat($existing, $data)) {
                    $this->log($user, 'goat', $existing->uuid, 'unchanged', 'Idempotent goat retry.');

                    return $this->ok('goat', $existing, 'synced');
                }

                if (($data['resolution'] ?? null) === 'server') {
                    $this->log($user, 'goat', $existing->uuid, 'conflict', 'Device accepted the server goat.');

                    return $this->ok('goat', $existing, 'synced', true);
                }

                if (($data['resolution'] ?? null) !== 'client' && $this->serverIsAhead($existing, $data['base_updated_at'] ?? null, $user)) {
                    $this->log($user, 'goat', $existing->uuid, 'conflict', 'Server goat is newer than the device copy.');

                    return $this->conflict('goat', $existing);
                }

                if ($this->goatCodeTaken($data['farm_uuid'], $data['goat_code'], $existing->uuid)) {
                    return $this->rejected('goat', $data['uuid'], 'Goat ID is already used on this farm. Edit the Goat ID and sync again.');
                }

                $this->writeGoat($existing, $data, $user, false);
                $this->log($user, 'goat', $existing->uuid, 'updated', 'Goat updated from field sync.');

                return $this->ok('goat', $existing, 'synced');
            });
        } catch (Throwable $exception) {
            report($exception);

            return $this->rejected(
                'goat',
                $payload['uuid'] ?? null,
                config('app.debug') ? $exception->getMessage() : 'The server could not save this goat. It is still stored on this device.'
            );
        }
    }

    public function presentFarm(Farm $farm): array
    {
        return [
            'id' => $farm->id,
            'uuid' => $farm->uuid,
            'farm_code' => $farm->farm_code,
            'ward_number' => $farm->ward_number,
            'total_goats' => $farm->total_goats,
            'feeding_system' => $farm->feeding_system,
            'deworming_frequency' => $farm->deworming_frequency,
            'common_anthelmintic' => $farm->common_anthelmintic,
            'other_anthelmintic' => $farm->other_anthelmintic,
            'schedule_decision_maker' => $farm->schedule_decision_maker,
            'other_decision_maker' => $farm->other_decision_maker,
            'dose_determination_method' => $farm->dose_determination_method,
            'created_by' => $farm->created_by,
            'updated_by' => $farm->updated_by,
            'sampled_goats' => $farm->goats_count ?? null,
            'created_at' => optional($farm->created_at)->toIso8601String(),
            'updated_at' => optional($farm->updated_at)->toIso8601String(),
            'deleted_at' => optional($farm->deleted_at)->toIso8601String(),
            'server_updated_at' => optional($farm->updated_at)->toIso8601String(),
        ];
    }

    public function presentGoat(Goat $goat): array
    {
        return [
            'id' => $goat->id,
            'uuid' => $goat->uuid,
            'farm_uuid' => $goat->farm_uuid,
            'farm_code' => $goat->relationLoaded('farm') ? $goat->farm?->farm_code : null,
            'goat_code' => $goat->goat_code,
            'age_value' => $goat->age_value,
            'age_unit' => $goat->age_unit,
            'sex' => $goat->sex,
            'breed' => $goat->breed,
            'other_breed' => $goat->other_breed,
            'body_condition_score' => $goat->body_condition_score,
            'last_deworming_duration' => $goat->last_deworming_duration,
            'last_deworming_medicine' => $goat->last_deworming_medicine,
            'other_last_deworming_medicine' => $goat->other_last_deworming_medicine,
            'deworming_frequency_last_12_months' => $goat->deworming_frequency_last_12_months,
            'created_by' => $goat->created_by,
            'updated_by' => $goat->updated_by,
            'created_at' => optional($goat->created_at)->toIso8601String(),
            'updated_at' => optional($goat->updated_at)->toIso8601String(),
            'deleted_at' => optional($goat->deleted_at)->toIso8601String(),
            'server_updated_at' => optional($goat->updated_at)->toIso8601String(),
        ];
    }

    private function deleteFarm(array $payload, User $user): array
    {
        $validator = Validator::make($payload, SurveyRules::deletion());
        if ($validator->fails()) {
            return $this->rejected('farm', $payload['uuid'] ?? null, $validator->errors()->first());
        }

        $data = $validator->validated();

        return DB::transaction(function () use ($data, $user) {
            $existing = Farm::withTrashed()->where('uuid', $data['uuid'])->lockForUpdate()->first();

            if (! $existing) {
                return [
                    'entity' => 'farm',
                    'uuid' => $data['uuid'],
                    'status' => 'synced',
                    'server_updated_at' => null,
                    'id' => null,
                    'message' => 'Farm was not on the server.',
                ];
            }

            if ($existing->trashed()) {
                return $this->ok('farm', $existing, 'synced');
            }

            if (($data['resolution'] ?? null) === 'server') {
                return $this->ok('farm', $existing, 'synced', true);
            }

            if (($data['resolution'] ?? null) !== 'client' && $this->serverIsAhead($existing, $data['base_updated_at'] ?? null, $user)) {
                return $this->conflict('farm', $existing);
            }

            $now = now();
            $existing->timestamps = false;
            $existing->deleted_at = $now;
            $existing->updated_at = $now;
            $existing->updated_by = $user->id;
            $existing->client_updated_at = isset($data['updated_at']) ? Carbon::parse($data['updated_at']) : $now;
            $existing->save();

            Goat::query()->where('farm_uuid', $existing->uuid)->each(function (Goat $goat) use ($user, $now) {
                $goat->timestamps = false;
                $goat->deleted_at = $now;
                $goat->updated_at = $now;
                $goat->updated_by = $user->id;
                $goat->save();
            });

            $this->log($user, 'farm', $existing->uuid, 'deleted', 'Farm archived from field sync.');

            return $this->ok('farm', $existing, 'synced');
        });
    }

    private function deleteGoat(array $payload, User $user): array
    {
        $validator = Validator::make($payload, SurveyRules::deletion());
        if ($validator->fails()) {
            return $this->rejected('goat', $payload['uuid'] ?? null, $validator->errors()->first());
        }

        $data = $validator->validated();

        return DB::transaction(function () use ($data, $user) {
            $existing = Goat::withTrashed()->where('uuid', $data['uuid'])->lockForUpdate()->first();

            if (! $existing || $existing->trashed()) {
                return [
                    'entity' => 'goat',
                    'uuid' => $data['uuid'],
                    'status' => 'synced',
                    'server_updated_at' => $existing ? optional($existing->updated_at)->toIso8601String() : null,
                    'id' => $existing?->id,
                    'message' => 'Goat was not active on the server.',
                ];
            }

            if (($data['resolution'] ?? null) === 'server') {
                return $this->ok('goat', $existing, 'synced', true);
            }

            if (($data['resolution'] ?? null) !== 'client' && $this->serverIsAhead($existing, $data['base_updated_at'] ?? null, $user)) {
                return $this->conflict('goat', $existing);
            }

            $now = now();
            $existing->timestamps = false;
            $existing->deleted_at = $now;
            $existing->updated_at = $now;
            $existing->updated_by = $user->id;
            $existing->client_updated_at = isset($data['updated_at']) ? Carbon::parse($data['updated_at']) : $now;
            $existing->save();
            $this->log($user, 'goat', $existing->uuid, 'deleted', 'Goat archived from field sync.');

            return $this->ok('goat', $existing, 'synced');
        });
    }

    private function writeFarm(Farm $farm, array $data, User $user, bool $creating): void
    {
        $farm->timestamps = false;
        $farm->fill($this->farmAttributes($data));
        $farm->uuid = $data['uuid'];
        if ($creating) {
            $farm->created_by = $user->id;
            $farm->created_at = Carbon::parse($data['created_at']);
        }
        $farm->updated_by = $user->id;
        $farm->client_updated_at = Carbon::parse($data['updated_at']);
        $farm->updated_at = now();
        $farm->deleted_at = null;
        $farm->save();
    }

    private function writeGoat(Goat $goat, array $data, User $user, bool $creating): void
    {
        $goat->timestamps = false;
        $goat->fill($this->goatAttributes($data));
        $goat->uuid = $data['uuid'];
        if ($creating) {
            $goat->created_by = $user->id;
            $goat->created_at = Carbon::parse($data['created_at']);
        }
        $goat->updated_by = $user->id;
        $goat->client_updated_at = Carbon::parse($data['updated_at']);
        $goat->updated_at = now();
        $goat->deleted_at = null;
        $goat->save();
    }

    private function farmAttributes(array $data): array
    {
        return [
            'farm_code' => $data['farm_code'],
            'ward_number' => $data['ward_number'],
            'total_goats' => $data['total_goats'],
            'feeding_system' => $data['feeding_system'],
            'deworming_frequency' => $data['deworming_frequency'],
            'common_anthelmintic' => $data['common_anthelmintic'],
            'other_anthelmintic' => $data['other_anthelmintic'] ?? null,
            'schedule_decision_maker' => $data['schedule_decision_maker'],
            'other_decision_maker' => $data['other_decision_maker'] ?? null,
            'dose_determination_method' => $data['dose_determination_method'],
        ];
    }

    private function goatAttributes(array $data): array
    {
        return [
            'farm_uuid' => $data['farm_uuid'],
            'goat_code' => $data['goat_code'],
            'age_value' => $data['age_value'],
            'age_unit' => $data['age_unit'],
            'sex' => $data['sex'],
            'breed' => $data['breed'],
            'other_breed' => $data['other_breed'] ?? null,
            'body_condition_score' => $data['body_condition_score'],
            'last_deworming_duration' => $data['last_deworming_duration'],
            'last_deworming_medicine' => $data['last_deworming_medicine'],
            'other_last_deworming_medicine' => $data['other_last_deworming_medicine'] ?? null,
            'deworming_frequency_last_12_months' => $data['deworming_frequency_last_12_months'],
        ];
    }

    private function normalizeFarm(array $data): array
    {
        $data['farm_code'] = trim($data['farm_code']);
        if ($data['common_anthelmintic'] !== 'other') {
            $data['other_anthelmintic'] = null;
        }
        if ($data['schedule_decision_maker'] !== 'other') {
            $data['other_decision_maker'] = null;
        }

        return $data;
    }

    private function normalizeGoat(array $data): array
    {
        $data['goat_code'] = trim($data['goat_code']);
        if ($data['breed'] !== 'other') {
            $data['other_breed'] = null;
        }
        if ($data['last_deworming_medicine'] !== 'other') {
            $data['other_last_deworming_medicine'] = null;
        }

        return $data;
    }

    private function sameFarm(Farm $farm, array $data): bool
    {
        if ($farm->trashed()) {
            return false;
        }

        foreach ($this->farmAttributes($data) as $key => $value) {
            if ($this->norm($farm->{$key}) !== $this->norm($value)) {
                return false;
            }
        }

        return true;
    }

    private function sameGoat(Goat $goat, array $data): bool
    {
        if ($goat->trashed()) {
            return false;
        }

        foreach ($this->goatAttributes($data) as $key => $value) {
            if ($this->norm($goat->{$key}) !== $this->norm($value)) {
                return false;
            }
        }

        return true;
    }

    private function serverIsAhead(Model $existing, ?string $base, User $user): bool
    {
        if ($existing->updated_at === null) {
            return false;
        }

        if ($base === null || $base === '') {
            return (int) $existing->updated_by !== (int) $user->id;
        }

        try {
            $baseAt = Carbon::parse($base);
        } catch (Throwable) {
            return true;
        }

        return $existing->updated_at->gt($baseAt->copy()->addSecond());
    }

    private function farmCodeTaken(string $code, ?string $exceptUuid = null): bool
    {
        return Farm::withTrashed()
            ->where('farm_code', $code)
            ->when($exceptUuid, fn ($query) => $query->where('uuid', '!=', $exceptUuid))
            ->exists();
    }

    private function goatCodeTaken(string $farmUuid, string $code, ?string $exceptUuid = null): bool
    {
        return Goat::withTrashed()
            ->where('farm_uuid', $farmUuid)
            ->where('goat_code', $code)
            ->when($exceptUuid, fn ($query) => $query->where('uuid', '!=', $exceptUuid))
            ->exists();
    }

    private function ok(string $entity, Model $model, string $status, bool $replaced = false): array
    {
        $record = $entity === 'farm'
            ? $this->presentFarm($model)
            : $this->presentGoat($model->loadMissing('farm'));

        return [
            'entity' => $entity,
            'uuid' => $model->uuid,
            'status' => $status,
            'replaced' => $replaced,
            'id' => $model->id,
            'server_updated_at' => optional($model->updated_at)->toIso8601String(),
            'record' => $replaced ? $record : null,
        ];
    }

    private function conflict(string $entity, Model $model): array
    {
        $record = $entity === 'farm'
            ? $this->presentFarm($model)
            : $this->presentGoat($model->loadMissing('farm'));

        return [
            'entity' => $entity,
            'uuid' => $model->uuid,
            'status' => 'conflict',
            'id' => $model->id,
            'server_updated_at' => optional($model->updated_at)->toIso8601String(),
            'message' => 'The server has a newer version of this record. Choose which copy to keep. Nothing was overwritten.',
            'record' => $record,
        ];
    }

    private function rejected(string $entity, ?string $uuid, string $message): array
    {
        return [
            'entity' => $entity,
            'uuid' => $uuid,
            'status' => 'rejected',
            'message' => $message,
        ];
    }

    private function log(User $user, string $entity, string $uuid, string $action, string $message): void
    {
        SyncLog::query()->create([
            'user_id' => $user->id,
            'entity' => $entity,
            'record_uuid' => $uuid,
            'action' => $action,
            'message' => $message,
        ]);
    }

    private function blankToNull(array $payload): array
    {
        foreach ($payload as $key => $value) {
            if ($value === '') {
                $payload[$key] = null;
            }
        }

        return $payload;
    }

    private function norm(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? '1' : '0';
        }

        return trim((string) $value);
    }
}
