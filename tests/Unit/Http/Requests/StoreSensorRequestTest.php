<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreSensorRequest;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class StoreSensorRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => 5]);
        $this->actingAs($user);

        $request = new StoreSensorRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => 10]);
        $this->actingAs($user);

        $request = new StoreSensorRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_prepareForValidationはprocess_idを設定しtriggerを正規化する(): void
    {
        $process = Process::factory()->create();
        $request = $this->makeRequest(['trigger' => '1'], $process);

        $this->invokePrepareForValidation($request);

        $this->assertSame($process->process_id, $request->input('process_id'));
        $this->assertTrue($request->boolean('trigger'));
    }

    public function test_prepareForValidationはルート工程なしでも例外にならない(): void
    {
        $request = StoreSensorRequest::create('/dummy', 'POST', ['trigger' => '0']);

        $this->invokePrepareForValidation($request);

        $this->assertNull($request->input('process_id'));
    }

    public function test_rulesは同一ラズパイ内のidentification_number重複を拒否する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        Sensor::query()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'identification_number' => 10,
            'alarm_text' => 'alarm old',
            'trigger' => true,
        ]);

        $request = new StoreSensorRequest();
        $request->merge(['raspberry_pi_id' => $raspberryPi->raspberry_pi_id]);

        $validator = Validator::make([
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'identification_number' => 10,
            'alarm_text' => 'alarm new',
            'trigger' => true,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('identification_number', $validator->errors()->toArray());
    }

    public function test_rulesは別ラズパイなら同じidentification_numberでも許可する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi1 = RaspberryPi::factory()->create();
        $raspberryPi2 = RaspberryPi::factory()->create();
        Sensor::query()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi1->raspberry_pi_id,
            'identification_number' => 20,
            'alarm_text' => 'alarm old',
            'trigger' => true,
        ]);

        $request = new StoreSensorRequest();
        $request->merge(['raspberry_pi_id' => $raspberryPi2->raspberry_pi_id]);

        $validator = Validator::make([
            'raspberry_pi_id' => $raspberryPi2->raspberry_pi_id,
            'identification_number' => 20,
            'alarm_text' => 'alarm new',
            'trigger' => false,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    private function makeRequest(array $data, Process $process): StoreSensorRequest
    {
        $request = StoreSensorRequest::create('/dummy', 'POST', $data);
        $request->setRouteResolver(fn() => new class($process) {
            public function __construct(private readonly Process $process) {}

            public function parameter(string $name): mixed
            {
                return $name === 'process' ? $this->process : null;
            }
        });

        return $request;
    }

    private function invokePrepareForValidation(StoreSensorRequest $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }
}
