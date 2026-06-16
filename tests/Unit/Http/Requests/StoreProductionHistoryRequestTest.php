<?php

namespace Tests\Unit\Http\Requests;

use App\Enums\ProductionStatus;
use App\Http\Requests\StoreProductionHistoryRequest;
use App\Models\CycleTime;
use App\Models\PartNumber;
use App\Models\Process;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class StoreProductionHistoryRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは常にtrueを返す(): void
    {
        $request = new StoreProductionHistoryRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_prepareForValidationはchangeover未指定でRUNNINGを設定する(): void
    {
        $process = Process::factory()->create();
        $request = $this->makeRequest([], $process);

        $this->invokePrepareForValidation($request);

        $this->assertSame($process->process_id, $request->input('process_id'));
        $this->assertEquals(ProductionStatus::RUNNING(), $request->input('status'));
    }

    public function test_prepareForValidationはchangeoverがtrueならCHANGEOVERを設定する(): void
    {
        $process = Process::factory()->create();
        $request = $this->makeRequest(['changeover' => '1'], $process);

        $this->invokePrepareForValidation($request);

        $this->assertEquals(ProductionStatus::CHANGEOVER(), $request->input('status'));
    }

    public function test_prepareForValidationはchangeoverが0ならRUNNINGを設定する(): void
    {
        $process = Process::factory()->create();
        $request = $this->makeRequest(['changeover' => '0'], $process);

        $this->invokePrepareForValidation($request);

        $this->assertEquals(ProductionStatus::RUNNING(), $request->input('status'));
    }

    public function test_rulesは同一工程に存在するpart_number_idを許可する(): void
    {
        $process = Process::factory()->create();
        $cycleTime = $this->createCycleTime($process);
        $request = new StoreProductionHistoryRequest();
        $request->merge(['process_id' => $process->process_id]);

        $validator = Validator::make([
            'part_number_id' => $cycleTime->part_number_id,
            'status' => ProductionStatus::RUNNING(),
            'goal' => 0,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesは別工程にしか存在しないpart_number_idを拒否する(): void
    {
        $process1 = Process::factory()->create();
        $process2 = Process::factory()->create();
        $cycleTime = $this->createCycleTime($process1);
        $request = new StoreProductionHistoryRequest();
        $request->merge(['process_id' => $process2->process_id]);

        $validator = Validator::make([
            'part_number_id' => $cycleTime->part_number_id,
            'status' => ProductionStatus::RUNNING(),
            'goal' => 0,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('part_number_id', $validator->errors()->toArray());
    }

    public function test_rulesはRUNNINGとCHANGEOVER以外のstatusを拒否する(): void
    {
        $process = Process::factory()->create();
        $cycleTime = $this->createCycleTime($process);
        $request = new StoreProductionHistoryRequest();
        $request->merge(['process_id' => $process->process_id]);

        $validator = Validator::make([
            'part_number_id' => $cycleTime->part_number_id,
            'status' => ProductionStatus::BREAKDOWN(),
            'goal' => 0,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('status', $validator->errors()->toArray());
    }

    private function makeRequest(array $data, Process $process): StoreProductionHistoryRequest
    {
        $request = StoreProductionHistoryRequest::create('/dummy', 'POST', $data);
        $request->setRouteResolver(fn() => new class($process) {
            public function __construct(private readonly Process $process) {}

            public function parameter(string $name): mixed
            {
                return $name === 'process' ? $this->process : null;
            }
        });

        return $request;
    }

    private function invokePrepareForValidation(StoreProductionHistoryRequest $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }

    private function createCycleTime(Process $process): CycleTime
    {
        $partNumber = PartNumber::factory()->create();

        return CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => 10.000,
            'over_time' => 10.500,
        ]);
    }
}
