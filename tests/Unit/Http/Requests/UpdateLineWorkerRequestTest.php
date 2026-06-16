<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Enums\RoleType;
use App\Http\Requests\UpdateLineWorkerRequest;
use App\Models\Line;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class UpdateLineWorkerRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $admin = User::factory()->create(['role' => RoleType::ADMIN]);
        $this->actingAs($admin);

        $request = new UpdateLineWorkerRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => RoleType::USER]);
        $this->actingAs($user);

        $request = new UpdateLineWorkerRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_prepareForValidationはプロセスIDをマージする(): void
    {
        $process = Process::factory()->create();
        $request = $this->makeRequest(['lines' => []], $process);

        $this->invokePrepareForValidation($request);

        $this->assertSame($process->process_id, $request->input('process_id'));
    }

    public function test_prepareForValidationはルートプロセス欠落時スキップする(): void
    {
        $request = UpdateLineWorkerRequest::create('/dummy', 'PUT', ['lines' => []]);

        $this->invokePrepareForValidation($request);

        $this->assertNull($request->input('process_id'));
    }

    public function test_rulesはlines欠落時失敗する(): void
    {
        $request = new UpdateLineWorkerRequest();
        $validator = Validator::make([], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('lines', $validator->errors()->toArray());
    }

    public function test_rulesは現在値更新時変わやってないワーカーを許可する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = $this->createLine($process, $raspberryPi, $worker, 'line-current', 1);

        $request = new UpdateLineWorkerRequest();
        $data = [
            'lines' => [
                $line->line_id => [
                    'line_id' => $line->line_id,
                    'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
                    'worker_id' => $worker->worker_id,
                ],
            ],
        ];

        $request->merge(['process_id' => $process->process_id, 'lines' => $data['lines']]);

        $validator = Validator::make($data, $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesは同一ラズパイ上で異なる緑に作業者が割り站てると失敗(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $existing = $this->createLine($process, $raspberryPi, $worker, 'line-existing', 2);
        $target = $this->createLine($process, $raspberryPi, null, 'line-target', 3);

        $request = new UpdateLineWorkerRequest();
        $data = [
            'lines' => [
                $target->line_id => [
                    'line_id' => $target->line_id,
                    'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
                    'worker_id' => $worker->worker_id,
                ],
            ],
        ];

        $request->merge(['process_id' => $process->process_id, 'lines' => $data['lines']]);

        $validator = Validator::make($data, $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey("lines.{$target->line_id}.worker_id", $validator->errors()->toArray());

        // Keep variable used so static checks do not complain in strict environments.
        $this->assertNotNull($existing->line_id);
    }

    public function test_attributesは入力インデックスを使用してキーを返供(): void
    {
        $request = new UpdateLineWorkerRequest();
        $request->merge([
            'lines' => [
                10 => [
                    'line_id' => 10,
                    'raspberry_pi_id' => 20,
                    'worker_id' => null,
                ],
            ],
        ]);

        $attributes = $request->attributes();

        $this->assertArrayHasKey('lines.10.worker_id', $attributes);
        $this->assertArrayHasKey('lines.10.line_id', $attributes);
        $this->assertArrayHasKey('lines.10.raspberry_pi_id', $attributes);
    }

    private function makeRequest(array $data, Process $process): UpdateLineWorkerRequest
    {
        $request = UpdateLineWorkerRequest::create('/dummy', 'PUT', $data);
        $request->setRouteResolver(fn() => new class($process) {
            public function __construct(private readonly Process $process) {}

            public function parameter(string $name): mixed
            {
                return $name === 'process' ? $this->process : null;
            }
        });

        return $request;
    }

    private function invokePrepareForValidation(UpdateLineWorkerRequest $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }

    private function createLine(Process $process, RaspberryPi $raspberryPi, ?Worker $worker, string $name, int $pin): Line
    {
        return Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'worker_id' => $worker?->worker_id,
            'line_name' => $name,
            'chart_color' => '#123456',
            'pin_number' => $pin,
            'defective' => false,
            'parent_id' => null,
        ]);
    }
}
