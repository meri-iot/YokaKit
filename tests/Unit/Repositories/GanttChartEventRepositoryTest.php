<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\GanttChartType;
use App\Models\GanttChart;
use App\Models\GanttChartEvent;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Repositories\GanttChartEventRepository;
use App\Services\Utility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GanttChartEventRepositoryTest extends TestCase
{
    use RefreshDatabase;

    private const DATABASE_TIMESTAMP_FORMAT = 'Y-m-d H:i:s.v';

    public function test_modelはGanttChartEventクラスを返す(): void
    {
        $repository = new GanttChartEventRepository();

        $this->assertSame(GanttChartEvent::class, $repository->model());
    }

    public function test_storeEventはガントチャートイベントを保存してモデルを返す(): void
    {
        $process = Process::factory()->create();
        $ganttChart = $this->createGanttChart($process, 1);
        $date = Carbon::create(2026, 4, 9, 12, 34, 56, 'UTC')->microseconds(789000);
        $repository = new GanttChartEventRepository();

        $stored = $repository->storeEvent($process->process_id, $ganttChart->gantt_chart_id, true, $date);

        $this->assertInstanceOf(GanttChartEvent::class, $stored);
        $this->assertSame($process->process_id, $stored->process_id);
        $this->assertSame($ganttChart->gantt_chart_id, $stored->gantt_chart_id);
        $this->assertTrue($stored->signal);
        $this->assertSame(Utility::format($date), Utility::format($stored->at));
        $this->assertDatabaseHas('gantt_chart_events', [
            'process_id' => $process->process_id,
            'gantt_chart_id' => $ganttChart->gantt_chart_id,
            'signal' => true,
            'at' => Utility::format($date),
        ]);
    }

    public function test_storeEventは保存失敗時にnullを返す(): void
    {
        $process = Process::factory()->create();
        $ganttChart = $this->createGanttChart($process, 2);
        $repository = new class extends GanttChartEventRepository
        {
            protected function storeModel(Model $model): bool
            {
                return false;
            }
        };

        $stored = $repository->storeEvent(
            $process->process_id,
            $ganttChart->gantt_chart_id,
            false,
            Carbon::create(2026, 4, 9, 13, 0, 0, 'UTC')
        );

        $this->assertNull($stored);
        $this->assertDatabaseCount('gantt_chart_events', 0);
    }

    public function test_getEventsは各ガントチャートの前後境界イベントを返し未存在時は範囲端を補完する(): void
    {
        $process = Process::factory()->create();
        $chartWithBoundaries = $this->createGanttChart($process, 3);
        $chartWithoutBoundaries = $this->createGanttChart($process, 4);
        $chartWithoutEvents = $this->createGanttChart($process, 5);
        $startDate = Carbon::create(2026, 4, 9, 8, 0, 0, 'UTC');
        $endDate = Carbon::create(2026, 4, 9, 10, 0, 0, 'UTC');

        GanttChartEvent::query()->create([
            'process_id' => $process->process_id,
            'gantt_chart_id' => $chartWithBoundaries->gantt_chart_id,
            'signal' => true,
            'at' => Utility::format($startDate->copy()->subMinutes(30)),
        ]);
        GanttChartEvent::query()->create([
            'process_id' => $process->process_id,
            'gantt_chart_id' => $chartWithBoundaries->gantt_chart_id,
            'signal' => false,
            'at' => Utility::format($endDate->copy()->addMinutes(15)),
        ]);
        GanttChartEvent::query()->create([
            'process_id' => $process->process_id,
            'gantt_chart_id' => $chartWithoutBoundaries->gantt_chart_id,
            'signal' => true,
            'at' => Utility::format($startDate->copy()->addMinutes(30)),
        ]);

        $repository = new GanttChartEventRepository();
        [$minEvents, $maxEvents] = $repository->getEvents([
            $chartWithBoundaries->gantt_chart_id,
            $chartWithoutBoundaries->gantt_chart_id,
            $chartWithoutEvents->gantt_chart_id,
        ], $startDate, $endDate);

        $this->assertSame(Utility::format($startDate->copy()->subMinutes(30), self::DATABASE_TIMESTAMP_FORMAT), $minEvents[$chartWithBoundaries->gantt_chart_id]['max_date']);
        $this->assertSame(Utility::format($endDate->copy()->addMinutes(15), self::DATABASE_TIMESTAMP_FORMAT), $maxEvents[$chartWithBoundaries->gantt_chart_id]['min_date']);

        $this->assertSame(Utility::format($startDate, self::DATABASE_TIMESTAMP_FORMAT), $minEvents[$chartWithoutBoundaries->gantt_chart_id]['max_date']);
        $this->assertSame(Utility::format($endDate, self::DATABASE_TIMESTAMP_FORMAT), $maxEvents[$chartWithoutBoundaries->gantt_chart_id]['min_date']);

        $this->assertSame(Utility::format($startDate, self::DATABASE_TIMESTAMP_FORMAT), $minEvents[$chartWithoutEvents->gantt_chart_id]['max_date']);
        $this->assertSame(Utility::format($endDate, self::DATABASE_TIMESTAMP_FORMAT), $maxEvents[$chartWithoutEvents->gantt_chart_id]['min_date']);
    }

    private function createGanttChart(Process $process, int $pinNumber): GanttChart
    {
        $raspberryPi = RaspberryPi::factory()->create();

        return GanttChart::query()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => $pinNumber,
            'chart_name' => "chart-{$pinNumber}",
            'chart_color' => '#123456',
            'chart_type' => GanttChartType::BASE(),
            'trigger' => true,
            'signal' => false,
            'order' => $pinNumber,
        ]);
    }
}
