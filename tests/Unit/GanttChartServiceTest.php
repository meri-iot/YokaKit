<?php

namespace Tests\Unit;

use App\Enums\GanttChartType;
use App\Models\GanttChart;
use App\Models\GanttChartEvent;
use App\Models\Process;
use App\Repositories\GanttChartEventRepository;
use App\Repositories\GanttChartRepository;
use App\Repositories\ProcessRepository;
use App\Repositories\RaspberryPiRepository;
use App\Services\GanttChartService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class GanttChartServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    public function test_getEventWithRangeは開放区間の終了時刻を検索終了日にクリップする(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-08 12:00:00'));

        $startDate = Carbon::parse('2026-04-01 00:00:00');
        $endDate = Carbon::parse('2026-04-02 00:00:00');

        $baseChart = new GanttChart([
            'process_id' => 1,
            'chart_name' => 'Base',
            'chart_type' => GanttChartType::BASE,
        ]);
        $baseChart->gantt_chart_id = 10;
        $baseChart->setRelation('ganttChartEvents', new EloquentCollection([
            new GanttChartEvent([
                'gantt_chart_id' => 10,
                'process_id' => 1,
                'signal' => true,
                'at' => Carbon::parse('2026-04-01 10:00:00'),
            ]),
        ]));

        $workChart = new GanttChart([
            'process_id' => 1,
            'chart_name' => 'Work',
            'chart_type' => GanttChartType::WORK,
        ]);
        $workChart->gantt_chart_id = 11;
        $workChart->setRelation('ganttChartEvents', new EloquentCollection([
            new GanttChartEvent([
                'gantt_chart_id' => 11,
                'process_id' => 1,
                'signal' => true,
                'at' => Carbon::parse('2026-04-01 12:00:00'),
            ]),
        ]));

        $process = new Process([
            'process_name' => 'P1',
        ]);
        $process->process_id = 1;
        $process->setRelation('ganttCharts', new EloquentCollection([$baseChart, $workChart]));

        $ganttChartRepository = Mockery::mock(GanttChartRepository::class);
        $ganttChartRepository
            ->shouldReceive('get')
            ->once()
            ->with(['process_id' => 1])
            ->andReturn(new EloquentCollection([$baseChart, $workChart]));

        $ganttChartEventRepository = Mockery::mock(GanttChartEventRepository::class);
        $ganttChartEventRepository
            ->shouldReceive('getEvents')
            ->once()
            ->andReturn([[], []]);

        $processRepository = Mockery::mock(ProcessRepository::class);
        $processRepository
            ->shouldReceive('ganttChartEvent')
            ->once()
            ->with(1, [], [])
            ->andReturn($process);

        $this->app->instance(GanttChartRepository::class, $ganttChartRepository);
        $this->app->instance(GanttChartEventRepository::class, $ganttChartEventRepository);
        $this->app->instance(ProcessRepository::class, $processRepository);
        $this->app->instance(RaspberryPiRepository::class, Mockery::mock(RaspberryPiRepository::class));

        $service = new GanttChartService(
            $processRepository,
            $ganttChartRepository,
            $ganttChartEventRepository,
            Mockery::mock(RaspberryPiRepository::class),
        );

        $result = $service->getEventWithRange($process, $startDate, $endDate);

        $this->assertSame(1440, $result->range);
        $this->assertSame(50_400_000, $result->ganttCharts[0]->base_span);
        $this->assertSame(0, $result->ganttCharts[0]->work_span);
        $this->assertSame(0, $result->ganttCharts[0]->overlap_span);
        $this->assertSame(50_400_000, $result->ganttCharts[1]->base_span);
        $this->assertSame(43_200_000, $result->ganttCharts[1]->work_span);
        $this->assertSame(43_200_000, $result->ganttCharts[1]->overlap_span);
    }
}
