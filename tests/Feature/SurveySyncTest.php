<?php

namespace Tests\Feature;

use App\Models\Farm;
use App\Models\Goat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class SurveySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_confirms_the_api_is_reachable(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_researchers_can_sign_in_and_inactive_accounts_cannot(): void
    {
        $user = User::factory()->create([
            'email' => 'field@sundarbazar.test',
            'password' => 'secret-pass',
        ]);

        $this->postJson('/api/login', [
            'email' => 'field@sundarbazar.test',
            'password' => 'wrong-pass',
        ])->assertStatus(422);

        $this->postJson('/api/login', [
            'email' => 'field@sundarbazar.test',
            'password' => 'secret-pass',
        ])->assertOk()->assertJsonPath('user.email', 'field@sundarbazar.test');

        $user->forceFill(['is_active' => false])->save();

        $this->postJson('/api/login', [
            'email' => 'field@sundarbazar.test',
            'password' => 'secret-pass',
        ])->assertStatus(422);
    }

    public function test_researchers_cannot_manage_users(): void
    {
        $researcher = User::factory()->create();
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($researcher, 'sanctum')
            ->getJson('/api/users')
            ->assertForbidden();

        $this->actingAs($admin, 'sanctum')
            ->postJson('/api/users', [
                'name' => 'New Researcher',
                'email' => 'new@sundarbazar.test',
                'password' => 'Fieldpass1',
                'role' => 'researcher',
            ])
            ->assertCreated()
            ->assertJsonPath('email', 'new@sundarbazar.test');
    }

    public function test_sync_is_idempotent_for_a_farm_and_its_goats(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $farm = $this->farmPayload();
        $goat = $this->goatPayload($farm['uuid'], $farm['farm_code']);

        $this->postJson('/api/sync', ['farms' => [$farm], 'goats' => [$goat]])->assertOk();
        $this->postJson('/api/sync', ['farms' => [$farm], 'goats' => [$goat]])->assertOk()
            ->assertJsonPath('results.0.status', 'synced')
            ->assertJsonPath('results.1.status', 'synced');

        $this->assertSame(1, Farm::query()->count());
        $this->assertSame(1, Goat::query()->count());
        $this->assertSame($farm['uuid'], Goat::query()->first()->farm_uuid);
    }

    public function test_a_goat_is_not_stored_before_its_farm(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $goat = $this->goatPayload((string) Str::uuid(), 'SB01-MISSING');

        $this->postJson('/api/sync', ['farms' => [], 'goats' => [$goat]])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'blocked');

        $this->assertSame(0, Goat::query()->count());
    }

    public function test_invalid_ward_numbers_are_rejected_without_saving(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $farm = $this->farmPayload(['ward_number' => 99]);

        $this->postJson('/api/sync', ['farms' => [$farm], 'goats' => []])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'rejected');

        $this->assertSame(0, Farm::query()->count());
    }

    public function test_ten_farms_and_fifty_goats_sync_once_and_do_not_duplicate(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        [$farms, $goats] = $this->cohort();

        $first = $this->postJson('/api/sync', ['farms' => $farms, 'goats' => $goats]);
        $first->assertOk();
        $this->assertCount(60, $first->json('results'));
        $this->assertSame(
            60,
            collect($first->json('results'))->where('status', 'synced')->count()
        );

        $this->postJson('/api/sync', ['farms' => $farms, 'goats' => $goats])->assertOk();

        $this->assertSame(10, Farm::query()->count());
        $this->assertSame(50, Goat::query()->count());
        $this->assertSame(5, Goat::query()->where('farm_uuid', $farms[0]['uuid'])->count());
    }

    public function test_newer_server_data_is_not_overwritten_until_the_device_chooses(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($user, 'sanctum');
        $payload = $this->farmPayload();

        $created = $this->postJson('/api/sync', ['farms' => [$payload], 'goats' => []])->assertOk();
        $base = $created->json('results.0.server_updated_at');

        $farm = Farm::query()->first();
        $farm->timestamps = false;
        $farm->total_goats = 80;
        $farm->updated_by = $other->id;
        $farm->updated_at = now()->addMinutes(5);
        $farm->save();

        $payload['total_goats'] = 15;
        $payload['base_updated_at'] = $base;
        $payload['updated_at'] = now()->toIso8601String();

        $this->postJson('/api/sync', ['farms' => [$payload], 'goats' => []])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'conflict');

        $this->assertSame(80, Farm::query()->first()->total_goats);

        $payload['resolution'] = 'server';
        $this->postJson('/api/sync', ['farms' => [$payload], 'goats' => []])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced')
            ->assertJsonPath('results.0.replaced', true)
            ->assertJsonPath('results.0.record.total_goats', 80);

        $this->assertSame(80, Farm::query()->first()->total_goats);

        $payload['resolution'] = 'client';
        $payload['base_updated_at'] = Farm::query()->first()->updated_at->toIso8601String();
        $this->postJson('/api/sync', ['farms' => [$payload], 'goats' => []])
            ->assertOk()
            ->assertJsonPath('results.0.status', 'synced');

        $this->assertSame(15, Farm::query()->first()->total_goats);
        $this->assertSame(1, Farm::query()->count());
    }

    public function test_deleting_a_farm_archives_its_goats_and_can_be_retried(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        $farm = $this->farmPayload();
        $goat = $this->goatPayload($farm['uuid'], $farm['farm_code']);
        $this->postJson('/api/sync', ['farms' => [$farm], 'goats' => [$goat]])->assertOk();

        $delete = [
            'uuid' => $farm['uuid'],
            'deleted_at' => now()->toIso8601String(),
            'updated_at' => now()->toIso8601String(),
            'base_updated_at' => Farm::query()->first()->updated_at->toIso8601String(),
        ];

        $this->postJson('/api/sync', ['farms' => [$delete], 'goats' => []])->assertOk()
            ->assertJsonPath('results.0.status', 'synced');
        $this->postJson('/api/sync', ['farms' => [$delete], 'goats' => []])->assertOk();

        $this->assertSame(0, Farm::query()->count());
        $this->assertSame(0, Goat::query()->count());
        $this->assertNotNull(Farm::withTrashed()->first()->deleted_at);
        $this->assertNotNull(Goat::withTrashed()->first()->deleted_at);
    }

    public function test_pull_and_exports_return_the_synced_cohort(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum');
        [$farms, $goats] = $this->cohort();
        $this->postJson('/api/sync', ['farms' => $farms, 'goats' => $goats])->assertOk();

        $this->getJson('/api/sync/pull')
            ->assertOk()
            ->assertJsonPath('farms.0.uuid', $farms[0]['uuid']);

        $this->getJson('/api/sync/summary')
            ->assertOk()
            ->assertJsonPath('farms', 10)
            ->assertJsonPath('goats', 50);

        $csv = $this->get('/api/reports/farms?format=csv');
        $csv->assertOk();
        $this->assertStringContainsString($farms[0]['farm_code'], $csv->streamedContent());

        $goatCsv = $this->get('/api/reports/goats?format=csv');
        $goatCsv->assertOk();
        $this->assertStringContainsString($goats[0]['goat_code'], $goatCsv->streamedContent());
        $this->assertStringContainsString('last_deworming_duration', $goatCsv->streamedContent());

        $xlsx = $this->get('/api/reports/goats?format=xlsx');
        $xlsx->assertOk();
        $this->assertStringStartsWith('PK', $xlsx->streamedContent());
    }

    private function cohort(): array
    {
        $farms = [];
        $goats = [];

        for ($i = 1; $i <= 10; $i++) {
            $ward = (($i - 1) % 11) + 1;
            $farm = $this->farmPayload([
                'farm_code' => sprintf('SB%02d-COHORT-%02d', $ward, $i),
                'ward_number' => $ward,
                'total_goats' => 20 + $i,
            ]);
            $farms[] = $farm;

            for ($g = 1; $g <= 5; $g++) {
                $goats[] = $this->goatPayload($farm['uuid'], $farm['farm_code'], [
                    'goat_code' => sprintf('%s-G%02d', $farm['farm_code'], $g),
                    'sex' => $g % 2 === 0 ? 'female' : 'male',
                    'breed' => $g === 5 ? 'crossbred' : 'local',
                    'body_condition_score' => (($g - 1) % 5) + 1,
                ]);
            }
        }

        return [$farms, $goats];
    }

    private function farmPayload(array $overrides = []): array
    {
        return array_merge([
            'uuid' => (string) Str::uuid(),
            'farm_code' => 'SB01-'.strtoupper(Str::random(6)),
            'ward_number' => 3,
            'total_goats' => 18,
            'feeding_system' => 'semi_intensive',
            'deworming_frequency' => 'every_2_3_months',
            'common_anthelmintic' => 'albendazole',
            'other_anthelmintic' => null,
            'schedule_decision_maker' => 'veterinarian',
            'other_decision_maker' => null,
            'dose_determination_method' => 'estimated_body_weight',
            'created_at' => now()->subHours(2)->toIso8601String(),
            'updated_at' => now()->subHour()->toIso8601String(),
            'deleted_at' => null,
            'base_updated_at' => null,
        ], $overrides);
    }

    private function goatPayload(string $farmUuid, string $farmCode, array $overrides = []): array
    {
        return array_merge([
            'uuid' => (string) Str::uuid(),
            'farm_uuid' => $farmUuid,
            'goat_code' => $farmCode.'-G01',
            'age_value' => 18,
            'age_unit' => 'months',
            'sex' => 'female',
            'breed' => 'local',
            'other_breed' => null,
            'body_condition_score' => 3,
            'last_deworming_duration' => '1_3_months',
            'last_deworming_medicine' => 'ivermectin',
            'other_last_deworming_medicine' => null,
            'deworming_frequency_last_12_months' => 'twice',
            'created_at' => now()->subHours(2)->toIso8601String(),
            'updated_at' => now()->subHour()->toIso8601String(),
            'deleted_at' => null,
            'base_updated_at' => null,
        ], $overrides);
    }
}
