<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreCycleTimeRequest;
use App\Models\CycleTime;
use App\Models\PartNumber;
use App\Models\Process;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class StoreCycleTimeRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => 5]);
        $this->actingAs($user);

        $request = new StoreCycleTimeRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => 10]);
        $this->actingAs($user);

        $request = new StoreCycleTimeRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_prepareForValidationはルート工程からprocess_idを設定する(): void
    {
        $process = Process::factory()->create();
        $request = StoreCycleTimeRequest::create('/dummy', 'POST');
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

    public function test_rulesは同一工程内の品番重複を拒否する(): void
    {
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => 10.000,
            'over_time' => 10.500,
        ]);

        $request = new StoreCycleTimeRequest();
        $request->merge(['process_id' => $process->process_id]);

        $validator = Validator::make([
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => 12.000,
            'over_time' => 12.500,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('part_number_id', $validator->errors()->toArray());
    }

    public function test_rulesは別工程なら同一品番でも許可する(): void
    {
        $process1 = Process::factory()->create();
        $process2 = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        CycleTime::factory()->create([
            'process_id' => $process1->process_id,
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => 10.000,
            'over_time' => 10.500,
        ]);

        $request = new StoreCycleTimeRequest();
        $request->merge(['process_id' => $process2->process_id]);

        $validator = Validator::make([
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => 12.000,
            'over_time' => 12.500,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesはover_timeがcycle_time以下だと失敗する(): void
    {
        $request = new StoreCycleTimeRequest();
        $request->merge(['process_id' => Process::factory()->create()->process_id]);
        $partNumber = PartNumber::factory()->create();

        $validator = Validator::make([
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => 10.000,
            'over_time' => 10.000,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('over_time', $validator->errors()->toArray());
    }

    public function test_rulesはpart_number_idが文字列だと失敗する(): void
    {
        $request = new StoreCycleTimeRequest();
        $request->merge(['process_id' => Process::factory()->create()->process_id]);

        $validator = Validator::make([
            'part_number_id' => 'abc',
            'cycle_time' => 10.000,
            'over_time' => 10.500,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('part_number_id', $validator->errors()->toArray());
    }
}
