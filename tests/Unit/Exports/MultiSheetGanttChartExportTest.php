<?php

namespace Tests\Unit\Exports;

use App\Exports\GanttChartExport;
use App\Exports\MultiSheetGanttChartExport;
use App\Models\GanttChart;
use App\Models\Process;
use App\Services\GanttChartService;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class MultiSheetGanttChartExportTest extends TestCase
{
    public function test_sheetsはサービス結果のチャート数だけシートを返す()
    {
        $process = new Process();
        $filteredProcess = new Process();
        $filteredProcess->setRelation('ganttCharts', collect([
            new GanttChart(['chart_name' => 'Chart A']),
            new GanttChart(['chart_name' => 'Chart B']),
        ]));

        $start = Carbon::create(2026, 4, 8, 0, 0, 0);
        $end = Carbon::create(2026, 4, 8, 23, 59, 59);

        $service = Mockery::mock(GanttChartService::class);
        $service->shouldReceive('getEventWithRange')
            ->once()
            ->with($process, $start, $end)
            ->andReturn($filteredProcess);

        $export = new MultiSheetGanttChartExport($service, $process, $start, $end);
        $sheets = $export->sheets();

        $this->assertCount(2, $sheets);
        $this->assertInstanceOf(GanttChartExport::class, $sheets[0]);
        $this->assertSame('Chart A', $sheets[0]->title());
        $this->assertSame('Chart B', $sheets[1]->title());
    }

    public function test_sheetsは重複するシート名を一意化する()
    {
        $process = new Process();
        $process->setRelation('ganttCharts', collect([
            new GanttChart(['chart_name' => '同名チャート']),
            new GanttChart(['chart_name' => '同名チャート']),
            new GanttChart(['chart_name' => '同名チャート']),
        ]));

        $start = Carbon::create(2026, 4, 8, 0, 0, 0);
        $end = Carbon::create(2026, 4, 8, 23, 59, 59);

        $service = Mockery::mock(GanttChartService::class);
        $service->shouldReceive('getEventWithRange')
            ->once()
            ->andReturn($process);

        $export = new MultiSheetGanttChartExport($service, $process, $start, $end);
        $sheets = $export->sheets();

        $titles = array_map(fn(GanttChartExport $sheet) => $sheet->title(), $sheets);
        $this->assertSame('同名チャート', $titles[0]);
        $this->assertSame('同名チャート (2)', $titles[1]);
        $this->assertSame('同名チャート (3)', $titles[2]);
    }

    public function test_sheetsは31文字制限下でも重複時に一意化される()
    {
        $longName = '1234567890123456789012345678901';
        $process = new Process();
        $process->setRelation('ganttCharts', collect([
            new GanttChart(['chart_name' => $longName]),
            new GanttChart(['chart_name' => $longName]),
        ]));

        $start = Carbon::create(2026, 4, 8, 0, 0, 0);
        $end = Carbon::create(2026, 4, 8, 23, 59, 59);

        $service = Mockery::mock(GanttChartService::class);
        $service->shouldReceive('getEventWithRange')
            ->once()
            ->andReturn($process);

        $export = new MultiSheetGanttChartExport($service, $process, $start, $end);
        $sheets = $export->sheets();

        $title1 = $sheets[0]->title();
        $title2 = $sheets[1]->title();

        $this->assertLessThanOrEqual(31, mb_strlen($title1));
        $this->assertLessThanOrEqual(31, mb_strlen($title2));
        $this->assertNotSame($title1, $title2);
        $this->assertStringEndsWith(' (2)', $title2);
    }
}
