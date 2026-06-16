<?php

namespace Tests\Unit\Http\Requests;

use App\Enums\GanttChartType;
use App\Http\Requests\StoreGanttChartRequest;
use App\Models\GanttChart;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use ReflectionMethod;
use Tests\TestCase;

class StoreGanttChartRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => 5]);
        $this->actingAs($user);

        $request = new StoreGanttChartRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => 10]);
        $this->actingAs($user);

        $request = new StoreGanttChartRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_prepareForValidationはルート工程IDとtrigger真偽値を設定する(): void
    {
        $process = Process::factory()->create();
        $request = StoreGanttChartRequest::create('/dummy', 'POST', ['trigger' => 'on']);
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
        $this->assertTrue($request->boolean('trigger'));
    }

    public function test_prepareForValidationはtrigger未指定をfalseに正規化する(): void
    {
        $process = Process::factory()->create();
        $request = StoreGanttChartRequest::create('/dummy', 'POST');
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

        $this->assertFalse($request->boolean('trigger'));
    }

    public function test_rulesは正しい入力を許可する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $request = new StoreGanttChartRequest();
        $request->merge(['process_id' => $process->process_id]);

        $validator = Validator::make([
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 1,
            'chart_name' => 'chart-a',
            'chart_color' => '#112233',
            'chart_type' => GanttChartType::WORK(),
            'trigger' => true,
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesは同一ラズパイの同一ピン番号を拒否する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $this->createGanttChart($process, $raspberryPi, 1, 'base-chart', GanttChartType::WORK());

        $request = new StoreGanttChartRequest();
        $request->merge(['raspberry_pi_id' => $raspberryPi->raspberry_pi_id]);

        $validator = Validator::make([
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 1,
            'chart_name' => 'new-chart',
            'chart_color' => '#123456',
            'chart_type' => GanttChartType::WORK(),
            'trigger' => true,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('pin_number', $validator->errors()->toArray());
    }

    public function test_rulesは同一工程の同一chart_nameを拒否する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $this->createGanttChart($process, $raspberryPi, 1, 'same-name', GanttChartType::WORK());

        $request = new StoreGanttChartRequest();
        $request->merge(['process_id' => $process->process_id]);

        $validator = Validator::make([
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 2,
            'chart_name' => 'same-name',
            'chart_color' => '#123456',
            'chart_type' => GanttChartType::WORK(),
            'trigger' => true,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('chart_name', $validator->errors()->toArray());
    }

    public function test_rulesは同一工程内のBASE種別重複を拒否する(): void
    {
        $process = Process::factory()->create();
        $raspberryPi1 = RaspberryPi::factory()->create();
        $raspberryPi2 = RaspberryPi::factory()->create();
        $this->createGanttChart($process, $raspberryPi1, 1, 'base-exists', GanttChartType::BASE());

        $request = new StoreGanttChartRequest();
        $request->merge(['process_id' => $process->process_id]);

        $validator = Validator::make([
            'raspberry_pi_id' => $raspberryPi2->raspberry_pi_id,
            'pin_number' => 2,
            'chart_name' => 'base-new',
            'chart_color' => '#123456',
            'chart_type' => GanttChartType::BASE(),
            'trigger' => true,
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('chart_type', $validator->errors()->toArray());
    }

    private function createGanttChart(Process $process, RaspberryPi $raspberryPi, int $pinNumber, string $chartName, $chartType): GanttChart
    {
        return GanttChart::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => $pinNumber,
            'chart_name' => $chartName,
            'chart_color' => '#000000',
            'chart_type' => $chartType,
            'trigger' => true,
        ]);
    }
}
