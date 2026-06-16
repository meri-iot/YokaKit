<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Enums\RoleType;
use App\Http\Requests\UpdateProcessRequest;
use App\Models\Process;
use App\Models\User;
use App\Services\Utility;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class UpdateProcessRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => RoleType::ADMIN]);
        $this->actingAs($user);

        $request = new UpdateProcessRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => RoleType::USER]);
        $this->actingAs($user);

        $request = new UpdateProcessRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_prepareForValidationはルートの工程IDを補完しcount_switchを正規化する(): void
    {
        $process = Process::factory()->create();
        $request = UpdateProcessRequest::create('/dummy', 'PUT', [
            'count_switch' => '0',
        ]);

        $request->setRouteResolver(fn() => new class($process) {
            public function __construct(private readonly Process $process) {}

            public function parameter(string $name): mixed
            {
                return $name === 'process' ? $this->process : null;
            }
        });

        $this->invokePrepareForValidation($request);

        $this->assertSame($process->process_id, $request->input('process_id'));
        $this->assertFalse($request->boolean('count_switch'));
    }

    public function test_prepareForValidationはルートパラメータ欠落時にスキップする(): void
    {
        $request = UpdateProcessRequest::create('/dummy', 'PUT', [
            'process_id' => 123,
            'count_switch' => '0',
        ]);

        $this->invokePrepareForValidation($request);

        $this->assertSame(123, $request->input('process_id'));
    }

    public function test_rulesは更新対象の同一値を許可する(): void
    {
        $process = Process::factory()->create([
            'process_name' => '工程A',
            'plan_color' => '#123456',
            'count_switch' => true,
            'range' => 60,
            'remark' => '備考',
        ]);

        $request = new UpdateProcessRequest();
        $request->merge(['process_id' => $process->process_id]);

        $validator = Validator::make([
            'process_name' => '工程A',
            'plan_color' => '#123456',
            'count_switch' => true,
            'range' => 60,
            'remark' => '備考',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesは工程名の重複を拒否する(): void
    {
        $existing = Process::factory()->create(['process_name' => '重複工程名']);
        $target = Process::factory()->create(['process_name' => '更新対象工程']);

        $request = new UpdateProcessRequest();
        $request->merge(['process_id' => $target->process_id]);

        $validator = Validator::make([
            'process_name' => '重複工程名',
            'plan_color' => '#654321',
            'count_switch' => true,
            'range' => 60,
            'remark' => null,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('process_name', $validator->errors()->toArray());
        $this->assertNotNull($existing->process_id);
    }

    public function test_rulesは無効なrangeを拒否する(): void
    {
        $target = Process::factory()->create();
        $invalidRange = max(Utility::ganttChartDisplayRangeMinutes()) + 1;

        $request = new UpdateProcessRequest();
        $request->merge(['process_id' => $target->process_id]);

        $validator = Validator::make([
            'process_name' => 'range-ng-process',
            'plan_color' => '#123456',
            'count_switch' => true,
            'range' => $invalidRange,
            'remark' => null,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('range', $validator->errors()->toArray());
    }

    public function test_rulesはremarkが配列だと失敗する(): void
    {
        $target = Process::factory()->create();

        $request = new UpdateProcessRequest();
        $request->merge(['process_id' => $target->process_id]);

        $validator = Validator::make([
            'process_name' => 'remark-array-ng',
            'plan_color' => '#123456',
            'count_switch' => true,
            'range' => 60,
            'remark' => ['invalid'],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('remark', $validator->errors()->toArray());
    }

    private function invokePrepareForValidation(UpdateProcessRequest $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }
}
