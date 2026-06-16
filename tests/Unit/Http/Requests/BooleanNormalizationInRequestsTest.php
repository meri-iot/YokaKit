<?php

namespace Tests\Unit\Http\Requests;

use App\Http\Requests\StoreGanttChartRequest;
use App\Http\Requests\StoreProcessRequest;
use App\Http\Requests\StoreSensorRequest;
use App\Http\Requests\UpdateAndonConfigRequest;
use App\Http\Requests\UpdateGanttChartRequest;
use App\Http\Requests\UpdateLineRequest;
use App\Http\Requests\UpdateProcessRequest;
use App\Http\Requests\UpdateSensorRequest;
use App\Models\GanttChart;
use App\Models\Line;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\Sensor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionMethod;
use Tests\TestCase;

class BooleanNormalizationInRequestsTest extends TestCase
{
    use RefreshDatabase;

    public function test_storeProcessRequestはcount_switchの0をfalseとして扱う(): void
    {
        $request = StoreProcessRequest::create('/dummy', 'POST', ['count_switch' => '0']);

        $this->invokePrepareForValidation($request);

        $this->assertFalse($request->boolean('count_switch'));
    }

    public function test_updateProcessRequestはcount_switchの0をfalseとして扱う(): void
    {
        $process = Process::factory()->create();
        $request = UpdateProcessRequest::create('/dummy', 'PUT', ['count_switch' => '0']);
        $request->setRouteResolver($this->routeResolver(['process' => $process]));

        $this->invokePrepareForValidation($request);

        $this->assertSame($process->process_id, $request->input('process_id'));
        $this->assertFalse($request->boolean('count_switch'));
    }

    public function test_storeSensorRequestはtriggerの0をfalseとして扱う(): void
    {
        $process = Process::factory()->create();
        $request = StoreSensorRequest::create('/dummy', 'POST', ['trigger' => '0']);
        $request->setRouteResolver($this->routeResolver(['process' => $process]));

        $this->invokePrepareForValidation($request);

        $this->assertSame($process->process_id, $request->input('process_id'));
        $this->assertFalse($request->boolean('trigger'));
    }

    public function test_updateSensorRequestはtriggerの0をfalseとして扱う(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $sensor = Sensor::query()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'identification_number' => 1,
            'alarm_text' => 'alarm',
            'trigger' => true,
        ]);

        $request = UpdateSensorRequest::create('/dummy', 'PUT', ['trigger' => '0']);
        $request->setRouteResolver($this->routeResolver([
            'process' => $process,
            'sensor' => $sensor,
        ]));

        $this->invokePrepareForValidation($request);

        $this->assertSame($sensor->sensor_id, $request->input('sensor_id'));
        $this->assertFalse($request->boolean('trigger'));
    }

    public function test_storeGanttChartRequestはtriggerの0をfalseとして扱う(): void
    {
        $process = Process::factory()->create();
        $request = StoreGanttChartRequest::create('/dummy', 'POST', ['trigger' => '0']);
        $request->setRouteResolver($this->routeResolver(['process' => $process]));

        $this->invokePrepareForValidation($request);

        $this->assertSame($process->process_id, $request->input('process_id'));
        $this->assertFalse($request->boolean('trigger'));
    }

    public function test_updateGanttChartRequestはtriggerの0をfalseとして扱う(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $ganttChart = GanttChart::query()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 10,
            'trigger' => true,
            'chart_name' => 'chart-update',
            'chart_color' => '#123456',
            'signal' => null,
            'chart_type' => 1,
        ]);

        $request = UpdateGanttChartRequest::create('/dummy', 'PUT', ['trigger' => '0']);
        $request->setRouteResolver($this->routeResolver([
            'process' => $process,
            'ganttChart' => $ganttChart,
        ]));

        $this->invokePrepareForValidation($request);

        $this->assertSame($ganttChart->gantt_chart_id, $request->input('gantt_chart_id'));
        $this->assertFalse($request->boolean('trigger'));
    }

    public function test_updateLineRequestはdefectiveの0をfalseとして扱う(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $line = Line::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'worker_id' => null,
            'line_name' => 'update-line-source',
            'chart_color' => '#111111',
            'pin_number' => 50,
            'defective' => false,
        ]);

        $request = UpdateLineRequest::create('/dummy', 'PUT', [
            'defective' => '0',
            'worker_id' => 123,
            'parent_id' => 999,
        ]);
        $request->setRouteResolver($this->routeResolver([
            'process' => $process,
            'line' => $line,
        ]));

        $this->invokePrepareForValidation($request);

        $this->assertFalse($request->boolean('defective'));
        $this->assertSame(123, $request->input('worker_id'));
        $this->assertNull($request->input('parent_id'));
    }

    public function test_updateAndonConfigRequestは表示フラグの0をfalseとして扱う(): void
    {
        $request = UpdateAndonConfigRequest::create('/dummy', 'PUT', [
            'is_show_part_number' => '0',
            'is_show_goal' => '1',
        ]);

        $this->invokePrepareForValidation($request);

        $this->assertFalse($request->boolean('is_show_part_number'));
        $this->assertTrue($request->boolean('is_show_goal'));
    }

    private function invokePrepareForValidation(object $request): void
    {
        $method = new ReflectionMethod($request, 'prepareForValidation');
        $method->setAccessible(true);
        $method->invoke($request);
    }

    private function routeResolver(array $parameters): \Closure
    {
        return fn() => new class($parameters) {
            /** @param array<string,mixed> $parameters */
            public function __construct(private readonly array $parameters) {}

            public function parameter(string $name): mixed
            {
                return $this->parameters[$name] ?? null;
            }
        };
    }
}
