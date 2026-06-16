<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Enums\RoleType;
use App\Http\Requests\UpdateWorkerRequest;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class UpdateWorkerRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => RoleType::ADMIN]);
        $this->actingAs($user);

        $request = new UpdateWorkerRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => RoleType::USER]);
        $this->actingAs($user);

        $request = new UpdateWorkerRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_prepareForValidationはルートIDを補完する(): void
    {
        $worker = Worker::factory()->create();
        $request = UpdateWorkerRequest::create('/dummy', 'PUT');

        $request->setRouteResolver(fn() => new class($worker) {
            public function __construct(private readonly Worker $worker) {}

            public function parameter(string $name): mixed
            {
                return $name === 'worker' ? $this->worker : null;
            }
        });

        $this->invokePrepareForValidation($request);

        $this->assertSame($worker->worker_id, $request->input('worker_id'));
    }

    public function test_prepareForValidationはルートパラメータ欠落時にスキップする(): void
    {
        $request = UpdateWorkerRequest::create('/dummy', 'PUT', [
            'worker_id' => 123,
        ]);

        $this->invokePrepareForValidation($request);

        $this->assertSame(123, $request->input('worker_id'));
    }

    public function test_rulesは更新対象の同一値を許可する(): void
    {
        $worker = Worker::factory()->create([
            'identification_number' => 'worker-current',
            'worker_name' => 'Current Worker',
            'mac_address' => '00:11:22:33:44:55',
        ]);

        $request = new UpdateWorkerRequest();
        $request->merge(['worker_id' => $worker->worker_id]);

        $validator = Validator::make([
            'identification_number' => 'worker-current',
            'worker_name' => 'Current Worker',
            'mac_address' => '00:11:22:33:44:55',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesは識別番号の重複を拒否する(): void
    {
        $existing = Worker::factory()->create(['identification_number' => 'dup-identification']);
        $target = Worker::factory()->create(['identification_number' => 'target-identification']);

        $request = new UpdateWorkerRequest();
        $request->merge(['worker_id' => $target->worker_id]);

        $validator = Validator::make([
            'identification_number' => 'dup-identification',
            'worker_name' => 'Updated Worker',
            'mac_address' => null,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('identification_number', $validator->errors()->toArray());
        $this->assertNotNull($existing->worker_id);
    }

    public function test_rulesはMACアドレス形式不正を拒否する(): void
    {
        $target = Worker::factory()->create();

        $request = new UpdateWorkerRequest();
        $request->merge(['worker_id' => $target->worker_id]);

        $validator = Validator::make([
            'identification_number' => 'worker-update-001',
            'worker_name' => 'Updated Worker',
            'mac_address' => 'invalid-mac',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('mac_address', $validator->errors()->toArray());
    }

    public function test_rulesはMACアドレスの重複を拒否する(): void
    {
        $existing = Worker::factory()->create([
            'identification_number' => 'worker-mac-existing',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ]);
        $target = Worker::factory()->create([
            'identification_number' => 'worker-mac-target',
            'mac_address' => '11:22:33:44:55:66',
        ]);

        $request = new UpdateWorkerRequest();
        $request->merge(['worker_id' => $target->worker_id]);

        $validator = Validator::make([
            'identification_number' => 'worker-mac-target',
            'worker_name' => 'Updated Worker',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('mac_address', $validator->errors()->toArray());
        $this->assertNotNull($existing->worker_id);
    }

    private function invokePrepareForValidation(UpdateWorkerRequest $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }
}
