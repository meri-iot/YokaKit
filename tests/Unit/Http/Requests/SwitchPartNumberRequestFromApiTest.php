<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\SwitchPartNumberRequestFromApi;
use App\Models\PartNumber;
use App\Models\Process;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class SwitchPartNumberRequestFromApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => 5]);
        $this->actingAs($user);

        $request = new SwitchPartNumberRequestFromApi();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => 10]);
        $this->actingAs($user);

        $request = new SwitchPartNumberRequestFromApi();

        $this->assertFalse($request->authorize());
    }

    public function test_prepareForValidationはforce_changeover未指定をtrueで補完する(): void
    {
        $request = SwitchPartNumberRequestFromApi::create('/dummy', 'POST');

        $this->invokePrepareForValidation($request);

        $this->assertTrue($request->boolean('force'));
        $this->assertTrue($request->boolean('changeover'));
    }

    public function test_prepareForValidationはforce_changeoverが0ならfalseとして扱う(): void
    {
        $request = SwitchPartNumberRequestFromApi::create('/dummy', 'POST', [
            'force' => '0',
            'changeover' => '0',
        ]);

        $this->invokePrepareForValidation($request);

        $this->assertFalse($request->boolean('force'));
        $this->assertFalse($request->boolean('changeover'));
    }

    public function test_rulesは実在する工程名と品番名を許可する(): void
    {
        $process = Process::factory()->create();
        $partNumber = PartNumber::factory()->create();
        $request = new SwitchPartNumberRequestFromApi();

        $validator = Validator::make([
            'processName' => $process->process_name,
            'partNumberName' => $partNumber->part_number_name,
            'goal' => 100,
            'force' => true,
            'changeover' => false,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesは存在しない工程名で失敗する(): void
    {
        $partNumber = PartNumber::factory()->create();
        $request = new SwitchPartNumberRequestFromApi();

        $validator = Validator::make([
            'processName' => 'not-found-process',
            'partNumberName' => $partNumber->part_number_name,
            'goal' => 100,
            'force' => true,
            'changeover' => true,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('processName', $validator->errors()->toArray());
    }

    public function test_failedValidationは400のjsonレスポンス例外を投げる(): void
    {
        $request = new SwitchPartNumberRequestFromApi();
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

    private function invokePrepareForValidation(SwitchPartNumberRequestFromApi $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }
}
