<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StopProductionRequest;
use App\Models\Process;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class StopProductionRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => 5]);
        $this->actingAs($user);

        $request = new StopProductionRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => 10]);
        $this->actingAs($user);

        $request = new StopProductionRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_rulesは実在する工程名を許可する(): void
    {
        $process = Process::factory()->create();
        $request = new StopProductionRequest();

        $validator = Validator::make([
            'processName' => $process->process_name,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesはprocessName未指定で失敗する(): void
    {
        $request = new StopProductionRequest();

        $validator = Validator::make([], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('processName', $validator->errors()->toArray());
    }

    public function test_rulesは存在しない工程名で失敗する(): void
    {
        $request = new StopProductionRequest();

        $validator = Validator::make([
            'processName' => 'not-found-process',
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('processName', $validator->errors()->toArray());
    }

    public function test_failedValidationは400のjsonレスポンス例外を投げる(): void
    {
        $request = new StopProductionRequest();
        $validator = Validator::make([], $request->rules());

        $this->assertTrue($validator->fails());

        $method = new ReflectionMethod($request, 'failedValidation');
        $method->setAccessible(true);

        try {
            $method->invoke($request, $validator);
            $this->fail('HttpResponseException was not thrown.');
        } catch (HttpResponseException $e) {
            $response = $e->getResponse();
            $this->assertSame(400, $response->getStatusCode());

            $decoded = json_decode($response->getContent(), true);
            $this->assertIsArray($decoded);
            $this->assertArrayHasKey('errors', $decoded);
            $this->assertArrayHasKey('processName', $decoded['errors']);
        }
    }
}
