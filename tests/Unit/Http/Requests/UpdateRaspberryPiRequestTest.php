<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Enums\RoleType;
use App\Http\Requests\UpdateRaspberryPiRequest;
use App\Models\RaspberryPi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class UpdateRaspberryPiRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => RoleType::ADMIN]);
        $this->actingAs($user);

        $request = new UpdateRaspberryPiRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => RoleType::USER]);
        $this->actingAs($user);

        $request = new UpdateRaspberryPiRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_prepareForValidationはルートIDを補完する(): void
    {
        $raspberryPi = RaspberryPi::factory()->create();
        $request = UpdateRaspberryPiRequest::create('/dummy', 'PUT');

        $request->setRouteResolver(fn() => new class($raspberryPi) {
            public function __construct(private readonly RaspberryPi $raspberryPi) {}

            public function parameter(string $name): mixed
            {
                return $name === 'raspberryPi' ? $this->raspberryPi : null;
            }
        });

        $this->invokePrepareForValidation($request);

        $this->assertSame($raspberryPi->raspberry_pi_id, $request->input('raspberry_pi_id'));
    }

    public function test_prepareForValidationはルートパラメータ欠落時にスキップする(): void
    {
        $request = UpdateRaspberryPiRequest::create('/dummy', 'PUT', [
            'raspberry_pi_id' => 123,
        ]);

        $this->invokePrepareForValidation($request);

        $this->assertSame(123, $request->input('raspberry_pi_id'));
    }

    public function test_rulesは更新対象の同一値を許可する(): void
    {
        $raspberryPi = RaspberryPi::factory()->create([
            'raspberry_pi_name' => 'raspi-current',
            'ip_address' => '192.168.50.10',
        ]);

        $request = new UpdateRaspberryPiRequest();
        $request->merge(['raspberry_pi_id' => $raspberryPi->raspberry_pi_id]);

        $validator = Validator::make([
            'raspberry_pi_name' => 'raspi-current',
            'ip_address' => '192.168.50.10',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesはラズパイ名の重複を拒否する(): void
    {
        $existing = RaspberryPi::factory()->create(['raspberry_pi_name' => 'raspi-dup-name']);
        $target = RaspberryPi::factory()->create(['raspberry_pi_name' => 'raspi-target-name']);

        $request = new UpdateRaspberryPiRequest();
        $request->merge(['raspberry_pi_id' => $target->raspberry_pi_id]);

        $validator = Validator::make([
            'raspberry_pi_name' => 'raspi-dup-name',
            'ip_address' => '192.168.50.11',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('raspberry_pi_name', $validator->errors()->toArray());
        $this->assertNotNull($existing->raspberry_pi_id);
    }

    public function test_rulesはIPアドレスの重複を拒否する(): void
    {
        $existing = RaspberryPi::factory()->create([
            'raspberry_pi_name' => 'raspi-existing-ip',
            'ip_address' => '192.168.50.12',
        ]);
        $target = RaspberryPi::factory()->create([
            'raspberry_pi_name' => 'raspi-target-ip',
            'ip_address' => '192.168.50.13',
        ]);

        $request = new UpdateRaspberryPiRequest();
        $request->merge(['raspberry_pi_id' => $target->raspberry_pi_id]);

        $validator = Validator::make([
            'raspberry_pi_name' => 'raspi-target-ip',
            'ip_address' => '192.168.50.12',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('ip_address', $validator->errors()->toArray());
        $this->assertNotNull($existing->raspberry_pi_id);
    }

    public function test_rulesはIPアドレス形式不正で失敗する(): void
    {
        $target = RaspberryPi::factory()->create();

        $request = new UpdateRaspberryPiRequest();
        $request->merge(['raspberry_pi_id' => $target->raspberry_pi_id]);

        $validator = Validator::make([
            'raspberry_pi_name' => 'raspi-invalid-ip',
            'ip_address' => 'invalid-ip-format',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('ip_address', $validator->errors()->toArray());
    }

    private function invokePrepareForValidation(UpdateRaspberryPiRequest $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }
}
