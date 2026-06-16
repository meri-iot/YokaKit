<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\GanttChartType;
use App\Models\GanttChart;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Repositories\GanttChartRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GanttChartRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_modelはGanttChartクラスを返す(): void
    {
        $repository = new GanttChartRepository();

        $this->assertSame(GanttChart::class, $repository->model());
    }

    public function test_sortは指定順に並べ替える(): void
    {
        $process = Process::factory()->create();
        $first = $this->createGanttChart($process, 1, 10);
        $second = $this->createGanttChart($process, 2, 11);
        $third = $this->createGanttChart($process, 3, 12);
        $repository = new GanttChartRepository();

        $repository->sort($process->process_id, [
            $third->gantt_chart_id,
            $first->gantt_chart_id,
            $second->gantt_chart_id,
        ]);

        $this->assertSame(0, GanttChart::query()->findOrFail($third->gantt_chart_id)->order);
        $this->assertSame(1, GanttChart::query()->findOrFail($first->gantt_chart_id)->order);
        $this->assertSame(2, GanttChart::query()->findOrFail($second->gantt_chart_id)->order);
    }

    public function test_sortは既存順と同じ指定でも例外を投げない(): void
    {
        $process = Process::factory()->create();
        $first = $this->createGanttChart($process, 4, 0);
        $second = $this->createGanttChart($process, 5, 1);
        $repository = new GanttChartRepository();

        $repository->sort($process->process_id, [
            $first->gantt_chart_id,
            $second->gantt_chart_id,
        ]);

        $this->assertSame(0, GanttChart::query()->findOrFail($first->gantt_chart_id)->order);
        $this->assertSame(1, GanttChart::query()->findOrFail($second->gantt_chart_id)->order);
    }

    public function test_sortは対象工程に存在しないIDが含まれると例外を投げてロールバックする(): void
    {
        $process = Process::factory()->create();
        $otherProcess = Process::factory()->create();
        $first = $this->createGanttChart($process, 6, 0);
        $second = $this->createGanttChart($process, 7, 1);
        $other = $this->createGanttChart($otherProcess, 8, 0);
        $repository = new GanttChartRepository();

        $this->expectException(ModelNotFoundException::class);

        try {
            $repository->sort($process->process_id, [
                $second->gantt_chart_id,
                $other->gantt_chart_id,
                $first->gantt_chart_id,
            ]);
        } finally {
            // 途中で更新されてもトランザクションでロールバックされることを確認する。
            $this->assertSame(0, GanttChart::query()->findOrFail($first->gantt_chart_id)->order);
            $this->assertSame(1, GanttChart::query()->findOrFail($second->gantt_chart_id)->order);
        }
    }

    private function createGanttChart(Process $process, int $pinNumber, int $order): GanttChart
    {
        $raspberryPi = RaspberryPi::factory()->create();

        $ganttChart = GanttChart::query()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => $pinNumber,
            'chart_name' => "chart-{$process->process_id}-{$pinNumber}",
            'chart_color' => '#ABCDEF',
            'chart_type' => GanttChartType::BASE(),
            'trigger' => true,
            'signal' => false,
        ]);

        GanttChart::query()
            ->where('gantt_chart_id', $ganttChart->gantt_chart_id)
            ->update(['order' => $order]);

        return $ganttChart->fresh();
    }
}
