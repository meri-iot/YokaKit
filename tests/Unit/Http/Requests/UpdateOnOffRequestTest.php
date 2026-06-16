<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Enums\RoleType;
use App\Http\Requests\UpdateOnOffRequest;
use App\Models\OnOff;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class UpdateOnOffRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => RoleType::ADMIN]);
        $this->actingAs($user);

        $request = new UpdateOnOffRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => RoleType::USER]);
        $this->actingAs($user);

        $request = new UpdateOnOffRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_prepareForValidationはルートIDをマージする(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $onOff = $this->createOnOff($process, $raspberryPi, 'event-a', 1);

        $request = UpdateOnOffRequest::create('/dummy', 'PUT');
        $request->setRouteResolver(fn() => new class($process, $onOff) {
            public function __construct(private readonly Process $process, private readonly OnOff $onOff) {}

            public function parameter(string $name): mixed
            {
                return match ($name) {
                    'process' => $this->process,
                    'onOff' => $this->onOff,
                    default => null,
                };
            }
        });

        $this->invokePrepareForValidation($request);

        $this->assertSame($process->process_id, $request->input('process_id'));
        $this->assertSame($onOff->on_off_id, $request->input('on_off_id'));
    }

    public function test_prepareForValidationはルートパラメータが欠落している場合スキップする(): void
    {
        $request = UpdateOnOffRequest::create('/dummy', 'PUT', ['process_id' => 100, 'on_off_id' => 200]);

        $this->invokePrepareForValidation($request);

        $this->assertSame(100, $request->input('process_id'));
        $this->assertSame(200, $request->input('on_off_id'));
    }

    public function test_rulesは更新時に現在のレコード値を許可する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $onOff = $this->createOnOff($process, $raspberryPi, 'same-event', 10);

        $request = new UpdateOnOffRequest();
        $request->merge([
            'process_id' => $process->process_id,
            'on_off_id' => $onOff->on_off_id,
        ]);

        $validator = Validator::make([
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'event_name' => 'same-event',
            'on_message' => 'on',
            'off_message' => 'off',
            'pin_number' => 10,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesは同一プロセス内の重複イベント名を拒否する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $existing = $this->createOnOff($process, $raspberryPi, 'duplicate-event', 20);
        $target = $this->createOnOff($process, $raspberryPi, 'target-event', 21);

        $request = new UpdateOnOffRequest();
        $request->merge([
            'process_id' => $process->process_id,
            'on_off_id' => $target->on_off_id,
        ]);

        $validator = Validator::make([
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'event_name' => 'duplicate-event',
            'on_message' => 'on',
            'off_message' => 'off',
            'pin_number' => 21,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('event_name', $validator->errors()->toArray());
        $this->assertNotNull($existing->on_off_id);
    }

    public function test_rulesは同一プロセス内の重複ピン番号を拒否する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $existing = $this->createOnOff($process, $raspberryPi, 'event-pin-a', 30);
        $target = $this->createOnOff($process, $raspberryPi, 'event-pin-b', 31);

        $request = new UpdateOnOffRequest();
        $request->merge([
            'process_id' => $process->process_id,
            'on_off_id' => $target->on_off_id,
        ]);

        $validator = Validator::make([
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'event_name' => 'event-pin-b',
            'on_message' => 'on',
            'off_message' => 'off',
            'pin_number' => 30,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('pin_number', $validator->errors()->toArray());
        $this->assertNotNull($existing->on_off_id);
    }

    private function invokePrepareForValidation(UpdateOnOffRequest $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }

    private function createOnOff(Process $process, RaspberryPi $raspberryPi, string $eventName, int $pinNumber): OnOff
    {
        return OnOff::query()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'event_name' => $eventName,
            'on_message' => 'on',
            'off_message' => 'off',
            'pin_number' => $pinNumber,
        ]);
    }
}
