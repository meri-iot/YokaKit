<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreOnOffRequest;
use App\Models\OnOff;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class StoreOnOffRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => 5]);
        $this->actingAs($user);

        $request = new StoreOnOffRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => 10]);
        $this->actingAs($user);

        $request = new StoreOnOffRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_prepareForValidationはルート工程からprocess_idを設定する(): void
    {
        $process = Process::factory()->create();
        $request = StoreOnOffRequest::create('/dummy', 'POST');
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

    public function test_rulesは同一工程内のevent_name重複を拒否する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $this->createOnOff($process, $raspberryPi, 'duplicate-event', 1);

        $request = new StoreOnOffRequest();
        $request->merge(['process_id' => $process->process_id]);

        $validator = Validator::make([
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'event_name' => 'duplicate-event',
            'on_message' => 'on message',
            'off_message' => 'off message',
            'pin_number' => 2,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('event_name', $validator->errors()->toArray());
    }

    public function test_rulesは工程が異なれば同じevent_nameでも許可する(): void
    {
        $process1 = Process::factory()->create();
        $process2 = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $this->createOnOff($process1, $raspberryPi, 'same-event', 3);

        $request = new StoreOnOffRequest();
        $request->merge(['process_id' => $process2->process_id]);

        $validator = Validator::make([
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'event_name' => 'same-event',
            'on_message' => 'on message',
            'off_message' => null,
            'pin_number' => 4,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesは同一工程内のpin_number重複を拒否する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $this->createOnOff($process, $raspberryPi, 'pin-event-1', 10);

        $request = new StoreOnOffRequest();
        $request->merge(['process_id' => $process->process_id]);

        $validator = Validator::make([
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'event_name' => 'pin-event-2',
            'on_message' => 'on message',
            'off_message' => 'off message',
            'pin_number' => 10,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('pin_number', $validator->errors()->toArray());
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
