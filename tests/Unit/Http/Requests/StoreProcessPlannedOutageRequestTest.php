<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreProcessPlannedOutageRequest;
use App\Models\PlannedOutage;
use App\Models\Process;
use App\Models\ProcessPlannedOutage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class StoreProcessPlannedOutageRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => 5]);
        $this->actingAs($user);

        $request = new StoreProcessPlannedOutageRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => 10]);
        $this->actingAs($user);

        $request = new StoreProcessPlannedOutageRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_prepareForValidationはルート工程からprocess_idを設定する(): void
    {
        $process = Process::factory()->create();
        $request = StoreProcessPlannedOutageRequest::create('/dummy', 'POST');
        $request->setRouteResolver(fn() => new class($process) {
            public function __construct(private readonly Process $process) {}

            public function parameter(string $name): mixed
            {
                return $name === 'process' ? $this->process : null;
            }
        });

        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertSame($process->process_id, $request->input('process_id'));
    }

    public function test_prepareForValidationはルート工程がない場合は何もしない(): void
    {
        $request = StoreProcessPlannedOutageRequest::create('/dummy', 'POST');

        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertNull($request->input('process_id'));
    }

    public function test_rulesは同一工程内のplanned_outage_id重複を拒否する(): void
    {
        $process = Process::factory()->create();
        $plannedOutage = PlannedOutage::factory()->create();
        ProcessPlannedOutage::query()->create([
            'process_id' => $process->process_id,
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ]);

        $request = new StoreProcessPlannedOutageRequest();
        $request->merge(['process_id' => $process->process_id]);

        $validator = Validator::make([
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('planned_outage_id', $validator->errors()->toArray());
    }

    public function test_rulesは工程が異なれば同じplanned_outage_idでも許可する(): void
    {
        $process1 = Process::factory()->create();
        $process2 = Process::factory()->create();
        $plannedOutage = PlannedOutage::factory()->create();
        ProcessPlannedOutage::query()->create([
            'process_id' => $process1->process_id,
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ]);

        $request = new StoreProcessPlannedOutageRequest();
        $request->merge(['process_id' => $process2->process_id]);

        $validator = Validator::make([
            'planned_outage_id' => $plannedOutage->planned_outage_id,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesは存在しないplanned_outage_idを拒否する(): void
    {
        $request = new StoreProcessPlannedOutageRequest();
        $request->merge(['process_id' => Process::factory()->create()->process_id]);

        $validator = Validator::make([
            'planned_outage_id' => 999999,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('planned_outage_id', $validator->errors()->toArray());
    }
}
