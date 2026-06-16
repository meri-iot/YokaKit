<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreLineRequest;
use App\Models\Line;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class StoreLineRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => 5]);
        $this->actingAs($user);

        $request = new StoreLineRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => 10]);
        $this->actingAs($user);

        $request = new StoreLineRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_prepareForValidationはdefectiveが0のときfalseとして扱う(): void
    {
        $process = Process::factory()->create();
        $worker = Worker::factory()->create();
        $request = $this->makeRequest([
            'line_name' => 'line-zero-defective',
            'chart_color' => '#123456',
            'raspberry_pi_id' => RaspberryPi::factory()->create()->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
            'pin_number' => 5,
            'defective' => '0',
            'parent_id' => 999999,
        ], $process);

        $this->invokePrepareForValidation($request);

        $this->assertSame($process->process_id, $request->input('process_id'));
        $this->assertFalse($request->boolean('defective'));
        $this->assertSame($worker->worker_id, $request->input('worker_id'));
        $this->assertNull($request->input('parent_id'));
    }

    public function test_prepareForValidationはdefectiveが1のときworker_idを破棄する(): void
    {
        $process = Process::factory()->create();
        $worker = Worker::factory()->create();
        $parentLine = $this->createParentLine($process);

        $request = $this->makeRequest([
            'line_name' => 'line-one-defective',
            'chart_color' => '#123456',
            'raspberry_pi_id' => RaspberryPi::factory()->create()->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
            'pin_number' => 6,
            'defective' => '1',
            'parent_id' => $parentLine->line_id,
        ], $process);

        $this->invokePrepareForValidation($request);

        $this->assertTrue($request->boolean('defective'));
        $this->assertNull($request->input('worker_id'));
        $this->assertSame($parentLine->line_id, $request->input('parent_id'));
    }

    public function test_rulesはdefectiveがtrueならparent_id必須で失敗する(): void
    {
        $process = Process::factory()->create();
        $request = new StoreLineRequest();
        $request->merge(['process_id' => $process->process_id]);

        $validator = Validator::make([
            'line_name' => 'line-missing-parent',
            'chart_color' => '#123456',
            'raspberry_pi_id' => RaspberryPi::factory()->create()->raspberry_pi_id,
            'worker_id' => null,
            'pin_number' => 7,
            'defective' => true,
            'parent_id' => null,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('parent_id', $validator->errors()->toArray());
    }

    public function test_rulesはdefectiveがfalseならparent_idなしで成功する(): void
    {
        $process = Process::factory()->create();
        $request = new StoreLineRequest();
        $request->merge(['process_id' => $process->process_id]);

        $validator = Validator::make([
            'line_name' => 'line-without-parent',
            'chart_color' => '#123456',
            'raspberry_pi_id' => RaspberryPi::factory()->create()->raspberry_pi_id,
            'worker_id' => null,
            'pin_number' => 8,
            'defective' => false,
            'parent_id' => null,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    private function makeRequest(array $data, Process $process): StoreLineRequest
    {
        $request = StoreLineRequest::create('/dummy', 'POST', $data);
        $request->setRouteResolver(fn() => new class($process) {
            public function __construct(private readonly Process $process) {}

            public function parameter(string $name): mixed
            {
                return $name === 'process' ? $this->process : null;
            }
        });

        return $request;
    }

    private function invokePrepareForValidation(StoreLineRequest $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }

    private function createParentLine(Process $process): Line
    {
        return Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => RaspberryPi::factory()->create()->raspberry_pi_id,
            'worker_id' => null,
            'line_name' => 'parent-line-' . uniqid(),
            'chart_color' => '#654321',
            'pin_number' => 20,
            'defective' => false,
        ]);
    }
}
