<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Enums\RoleType;
use App\Http\Requests\UpdateSensorRequest;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\Sensor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class UpdateSensorRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => RoleType::ADMIN]);
        $this->actingAs($user);

        $request = new UpdateSensorRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => RoleType::USER]);
        $this->actingAs($user);

        $request = new UpdateSensorRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_prepareForValidationはルートIDを補完しtriggerを正規化する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $sensor = $this->createSensor($process, $raspberryPi, 10, true);

        $request = UpdateSensorRequest::create('/dummy', 'PUT', ['trigger' => '0']);
        $request->setRouteResolver(fn() => new class($process, $sensor) {
            public function __construct(private readonly Process $process, private readonly Sensor $sensor) {}

            public function parameter(string $name): mixed
            {
                return match ($name) {
                    'process' => $this->process,
                    'sensor' => $this->sensor,
                    default => null,
                };
            }
        });

        $this->invokePrepareForValidation($request);

        $this->assertSame($process->process_id, $request->input('process_id'));
        $this->assertSame($sensor->sensor_id, $request->input('sensor_id'));
        $this->assertFalse($request->boolean('trigger'));
    }

    public function test_prepareForValidationはルートパラメータ欠落時にスキップする(): void
    {
        $request = UpdateSensorRequest::create('/dummy', 'PUT', [
            'process_id' => 123,
            'sensor_id' => 456,
            'trigger' => '0',
        ]);

        $this->invokePrepareForValidation($request);

        $this->assertSame(123, $request->input('process_id'));
        $this->assertSame(456, $request->input('sensor_id'));
        $this->assertSame('0', $request->input('trigger'));
    }

    public function test_rulesは更新対象の同一値を許可する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $sensor = $this->createSensor($process, $raspberryPi, 20, true);

        $request = new UpdateSensorRequest();
        $request->merge([
            'sensor_id' => $sensor->sensor_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
        ]);

        $validator = Validator::make([
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'identification_number' => 20,
            'alarm_text' => 'alarm-current',
            'trigger' => true,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesは同一ラズパイ内の識別番号重複を拒否する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $existing = $this->createSensor($process, $raspberryPi, 30, true);
        $target = $this->createSensor($process, $raspberryPi, 31, false);

        $request = new UpdateSensorRequest();
        $request->merge([
            'sensor_id' => $target->sensor_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
        ]);

        $validator = Validator::make([
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'identification_number' => 30,
            'alarm_text' => 'alarm-dup',
            'trigger' => true,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('identification_number', $validator->errors()->toArray());
        $this->assertNotNull($existing->sensor_id);
    }

    public function test_rulesは別ラズパイなら同じ識別番号でも許可する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi1 = RaspberryPi::factory()->create();
        $raspberryPi2 = RaspberryPi::factory()->create();

        $this->createSensor($process, $raspberryPi1, 40, true);
        $target = $this->createSensor($process, $raspberryPi2, 41, false);

        $request = new UpdateSensorRequest();
        $request->merge([
            'sensor_id' => $target->sensor_id,
            'raspberry_pi_id' => $raspberryPi2->raspberry_pi_id,
        ]);

        $validator = Validator::make([
            'raspberry_pi_id' => $raspberryPi2->raspberry_pi_id,
            'identification_number' => 40,
            'alarm_text' => 'alarm-ok',
            'trigger' => false,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesはtriggerがbooleanでない場合失敗する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $target = $this->createSensor($process, $raspberryPi, 50, true);

        $request = new UpdateSensorRequest();
        $request->merge([
            'sensor_id' => $target->sensor_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
        ]);

        $validator = Validator::make([
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'identification_number' => 50,
            'alarm_text' => 'alarm-invalid-trigger',
            'trigger' => 'not-bool',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('trigger', $validator->errors()->toArray());
    }

    private function invokePrepareForValidation(UpdateSensorRequest $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }

    private function createSensor(Process $process, RaspberryPi $raspberryPi, int $identificationNumber, bool $trigger): Sensor
    {
        return Sensor::query()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'identification_number' => $identificationNumber,
            'alarm_text' => 'alarm-text',
            'trigger' => $trigger,
        ]);
    }
}
