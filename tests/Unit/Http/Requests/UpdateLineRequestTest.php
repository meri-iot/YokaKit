<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests;

use App\Enums\RoleType;
use App\Http\Requests\UpdateLineRequest;
use App\Models\Line;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\User;
use App\Models\Worker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class UpdateLineRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => RoleType::ADMIN]);
        $this->actingAs($user);

        $request = new UpdateLineRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => RoleType::USER]);
        $this->actingAs($user);

        $request = new UpdateLineRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_prepareForValidationは非不良の不良判杖を行わない(): void
    {
        $process = Process::factory()->create();
        $line = $this->createLine($process, false);
        $worker = Worker::factory()->create();

        $request = $this->makeRequest([
            'line_name' => 'update-normal',
            'chart_color' => '#123456',
            'raspberry_pi_id' => $line->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
            'pin_number' => 10,
            'defective' => '0',
            'parent_id' => 999999,
        ], $process, $line);

        $this->invokePrepareForValidation($request);

        $this->assertSame($process->process_id, $request->input('process_id'));
        $this->assertSame($line->line_id, $request->input('line_id'));
        $this->assertFalse($request->boolean('defective'));
        $this->assertSame($worker->worker_id, $request->input('worker_id'));
        $this->assertNull($request->input('parent_id'));
    }

    public function test_prepareForValidationは不良時にworker_idを頴奔する(): void
    {
        $process = Process::factory()->create();
        $parentLine = $this->createLine($process, false);
        $line = $this->createLine($process, false);
        $worker = Worker::factory()->create();

        $request = $this->makeRequest([
            'line_name' => 'update-defective',
            'chart_color' => '#123456',
            'raspberry_pi_id' => $line->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
            'pin_number' => 11,
            'defective' => '1',
            'parent_id' => $parentLine->line_id,
        ], $process, $line);

        $this->invokePrepareForValidation($request);

        $this->assertTrue($request->boolean('defective'));
        $this->assertNull($request->input('worker_id'));
        $this->assertSame($parentLine->line_id, $request->input('parent_id'));
    }

    public function test_prepareForValidationはルートパラメータ欠落時スキップする(): void
    {
        $request = UpdateLineRequest::create('/dummy', 'POST', [
            'defective' => '1',
            'worker_id' => 1,
            'parent_id' => 2,
        ]);

        $this->invokePrepareForValidation($request);

        $this->assertSame('1', $request->input('defective'));
        $this->assertSame(1, $request->input('worker_id'));
        $this->assertSame(2, $request->input('parent_id'));
    }

    public function test_rulesは不良はtrueでparent_id必須にし失敗させる(): void
    {
        $process = Process::factory()->create();
        $line = $this->createLine($process, false);

        $request = new UpdateLineRequest();
        $request->merge([
            'process_id' => $process->process_id,
            'line_id' => $line->line_id,
            'raspberry_pi_id' => $line->raspberry_pi_id,
            'defective' => true,
        ]);

        $validator = Validator::make([
            'line_name' => 'update-missing-parent',
            'chart_color' => '#123456',
            'raspberry_pi_id' => $line->raspberry_pi_id,
            'worker_id' => null,
            'pin_number' => 12,
            'defective' => true,
            'parent_id' => null,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('parent_id', $validator->errors()->toArray());
    }

    public function test_rulesは不良はfalseでparent_idなし成功させる(): void
    {
        $process = Process::factory()->create();
        $line = $this->createLine($process, false);

        $request = new UpdateLineRequest();
        $request->merge([
            'process_id' => $process->process_id,
            'line_id' => $line->line_id,
            'raspberry_pi_id' => $line->raspberry_pi_id,
            'defective' => false,
        ]);

        $validator = Validator::make([
            'line_name' => 'update-without-parent',
            'chart_color' => '#123456',
            'raspberry_pi_id' => $line->raspberry_pi_id,
            'worker_id' => null,
            'pin_number' => 13,
            'defective' => false,
            'parent_id' => null,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesは様商一一一一法则の原其を無視する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $worker = Worker::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
            'line_name' => 'line-for-update',
            'chart_color' => '#123456',
            'pin_number' => 14,
            'defective' => false,
            'parent_id' => null,
        ]);

        $request = new UpdateLineRequest();
        $request->merge([
            'process_id' => $process->process_id,
            'line_id' => $line->line_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'defective' => false,
        ]);

        $validator = Validator::make([
            'line_name' => 'line-for-update',
            'chart_color' => '#234567',
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'worker_id' => $worker->worker_id,
            'pin_number' => 14,
            'defective' => false,
            'parent_id' => null,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    private function makeRequest(array $data, Process $process, Line $line): UpdateLineRequest
    {
        $request = UpdateLineRequest::create('/dummy', 'PUT', $data);
        $request->setRouteResolver(fn() => new class($process, $line) {
            public function __construct(private readonly Process $process, private readonly Line $line) {}

            public function parameter(string $name): mixed
            {
                return match ($name) {
                    'process' => $this->process,
                    'line' => $this->line,
                    default => null,
                };
            }
        });

        return $request;
    }

    private function invokePrepareForValidation(UpdateLineRequest $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }

    private function createLine(Process $process, bool $defective): Line
    {
        return Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => RaspberryPi::factory()->create()->raspberry_pi_id,
            'worker_id' => $defective ? null : Worker::factory()->create()->worker_id,
            'line_name' => 'line-' . uniqid('', true),
            'chart_color' => '#654321',
            'pin_number' => random_int(0, 127),
            'defective' => $defective,
            'parent_id' => null,
        ]);
    }
}
