<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreWorkerRequest;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class StoreWorkerRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => 5]);
        $this->actingAs($user);

        $request = new StoreWorkerRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => 10]);
        $this->actingAs($user);

        $request = new StoreWorkerRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_rulesは正常な入力を許可する(): void
    {
        $request = new StoreWorkerRequest();

        $validator = Validator::make([
            'identification_number' => 'worker-id-001',
            'worker_name' => 'worker name',
            'mac_address' => '00:11:22:33:44:55',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesはidentification_numberの重複を拒否する(): void
    {
        Worker::factory()->create(['identification_number' => 'dup-identification']);
        $request = new StoreWorkerRequest();

        $validator = Validator::make([
            'identification_number' => 'dup-identification',
            'worker_name' => 'worker name',
            'mac_address' => null,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('identification_number', $validator->errors()->toArray());
    }

    public function test_rulesはmac_address形式不正を拒否する(): void
    {
        $request = new StoreWorkerRequest();

        $validator = Validator::make([
            'identification_number' => 'worker-id-002',
            'worker_name' => 'worker name',
            'mac_address' => 'invalid-mac',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('mac_address', $validator->errors()->toArray());
    }

    public function test_rulesはmac_address重複を拒否する(): void
    {
        Worker::factory()->create([
            'identification_number' => 'worker-id-base',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ]);
        $request = new StoreWorkerRequest();

        $validator = Validator::make([
            'identification_number' => 'worker-id-new',
            'worker_name' => 'worker name',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('mac_address', $validator->errors()->toArray());
    }
}
