<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Enums\RoleType;
use App\Http\Requests\UpdatePlannedOutageRequest;
use App\Models\PlannedOutage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class UpdatePlannedOutageRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => RoleType::ADMIN]);
        $this->actingAs($user);

        $request = new UpdatePlannedOutageRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => RoleType::USER]);
        $this->actingAs($user);

        $request = new UpdatePlannedOutageRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_prepareForValidationはルートIDを補完する(): void
    {
        $plannedOutage = PlannedOutage::factory()->create();
        $request = UpdatePlannedOutageRequest::create('/dummy', 'PUT');

        $request->setRouteResolver(fn() => new class($plannedOutage) {
            public function __construct(private readonly PlannedOutage $plannedOutage) {}

            public function parameter(string $name): mixed
            {
                return $name === 'plannedOutage' ? $this->plannedOutage : null;
            }
        });

        $this->invokePrepareForValidation($request);

        $this->assertSame($plannedOutage->planned_outage_id, $request->input('planned_outage_id'));
    }

    public function test_prepareForValidationはルートパラメータ欠落時にスキップする(): void
    {
        $request = UpdatePlannedOutageRequest::create('/dummy', 'PUT', [
            'planned_outage_id' => 123,
        ]);

        $this->invokePrepareForValidation($request);

        $this->assertSame(123, $request->input('planned_outage_id'));
    }

    public function test_rulesは更新対象の同一値を許可する(): void
    {
        $plannedOutage = PlannedOutage::factory()->create([
            'planned_outage_name' => '昼休憩',
            'start_time' => '12:00',
            'end_time' => '13:00',
        ]);

        $request = new UpdatePlannedOutageRequest();
        $request->merge(['planned_outage_id' => $plannedOutage->planned_outage_id]);

        $validator = Validator::make([
            'planned_outage_name' => '昼休憩',
            'start_time' => '12:00',
            'end_time' => '13:00',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesは計画停止名の重複を拒否する(): void
    {
        $existing = PlannedOutage::factory()->create([
            'planned_outage_name' => '重複名',
        ]);
        $target = PlannedOutage::factory()->create([
            'planned_outage_name' => '更新対象',
        ]);

        $request = new UpdatePlannedOutageRequest();
        $request->merge(['planned_outage_id' => $target->planned_outage_id]);

        $validator = Validator::make([
            'planned_outage_name' => '重複名',
            'start_time' => '10:00',
            'end_time' => '11:00',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('planned_outage_name', $validator->errors()->toArray());
        $this->assertNotNull($existing->planned_outage_id);
    }

    public function test_rulesは開始時刻と終了時刻が同一なら失敗する(): void
    {
        $target = PlannedOutage::factory()->create();

        $request = new UpdatePlannedOutageRequest();
        $request->merge(['planned_outage_id' => $target->planned_outage_id]);

        $validator = Validator::make([
            'planned_outage_name' => '同一時刻検証',
            'start_time' => '12:00',
            'end_time' => '12:00',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('end_time', $validator->errors()->toArray());
    }

    private function invokePrepareForValidation(UpdatePlannedOutageRequest $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }
}
