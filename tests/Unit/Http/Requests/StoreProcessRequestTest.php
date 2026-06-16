<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreProcessRequest;
use App\Models\Process;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class StoreProcessRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => 5]);
        $this->actingAs($user);

        $request = new StoreProcessRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => 10]);
        $this->actingAs($user);

        $request = new StoreProcessRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_prepareForValidationはcount_switchの0をfalseとして正規化する(): void
    {
        $request = StoreProcessRequest::create('/dummy', 'POST', ['count_switch' => '0']);

        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);

        $this->assertFalse($request->boolean('count_switch'));
    }

    public function test_rulesは正常な入力を許可する(): void
    {
        $request = new StoreProcessRequest();

        $validator = Validator::make([
            'process_name' => 'process-store-ok',
            'plan_color' => '#123456',
            'count_switch' => true,
            'range' => 60,
            'remark' => '備考',
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesはprocess_nameの重複を拒否する(): void
    {
        Process::factory()->create(['process_name' => 'dup-process-name']);
        $request = new StoreProcessRequest();

        $validator = Validator::make([
            'process_name' => 'dup-process-name',
            'plan_color' => '#123456',
            'count_switch' => true,
            'range' => 1,
            'remark' => null,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('process_name', $validator->errors()->toArray());
    }

    public function test_rulesはremarkが配列だと失敗する(): void
    {
        $request = new StoreProcessRequest();

        $validator = Validator::make([
            'process_name' => 'remark-array-ng',
            'plan_color' => '#123456',
            'count_switch' => true,
            'range' => 1,
            'remark' => ['invalid'],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('remark', $validator->errors()->toArray());
    }
}
