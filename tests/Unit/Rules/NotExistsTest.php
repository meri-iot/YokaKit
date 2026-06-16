<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Enums\GanttChartType;
use App\Models\GanttChart;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Rules\NotExists;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class NotExistsTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 対象値以外を選んだ場合は既存データがあっても通過する。
     */
    public function test_対象値以外は重複チェックをスキップする(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $this->createGanttChart(
            $process->process_id,
            $raspberryPi->raspberry_pi_id,
            1,
            'Base chart',
            GanttChartType::BASE,
        );

        $validator = Validator::make(
            ['chart_type' => GanttChartType::WORK],
            ['chart_type' => [new NotExists('gantt_charts', 'chart_type', GanttChartType::BASE(), ['process_id' => $process->process_id])]],
        );

        $this->assertTrue($validator->passes());
    }

    /**
     * 対象値が既に同一条件で存在する場合は失敗する。
     */
    public function test_対象値が既に存在する場合はバリデーション失敗(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $this->createGanttChart(
            $process->process_id,
            $raspberryPi->raspberry_pi_id,
            1,
            'Base chart',
            GanttChartType::BASE,
        );

        $validator = Validator::make(
            ['chart_type' => GanttChartType::BASE],
            ['chart_type' => [new NotExists('gantt_charts', 'chart_type', GanttChartType::BASE(), ['process_id' => $process->process_id])]],
        );

        $this->assertFalse($validator->passes());
        $this->assertSame(__('validation.not_exists'), $validator->errors()->first('chart_type'));
    }

    /**
     * 除外条件に合致する既存レコードは更新時の重複判定から除外する。
     */
    public function test_除外条件に一致するレコードは重複判定から除外される(): void
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();
        $ganttChart = GanttChart::query()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 1,
            'chart_name' => 'Base chart',
            'chart_color' => '#112233',
            'chart_type' => GanttChartType::BASE,
            'trigger' => true,
        ]);

        $validator = Validator::make(
            ['chart_type' => GanttChartType::BASE],
            [
                'chart_type' => [
                    new NotExists(
                        'gantt_charts',
                        'chart_type',
                        GanttChartType::BASE(),
                        ['process_id' => $process->process_id],
                        ['gantt_chart_id' => $ganttChart->gantt_chart_id],
                    ),
                ],
            ],
        );

        $this->assertTrue($validator->passes());
    }

    /**
     * テスト用のガントチャートを作成する。
     */
    private function createGanttChart(
        int $processId,
        int $raspberryPiId,
        int $pinNumber,
        string $chartName,
        int $chartType,
    ): GanttChart {
        return GanttChart::query()->create([
            'process_id' => $processId,
            'raspberry_pi_id' => $raspberryPiId,
            'pin_number' => $pinNumber,
            'chart_name' => $chartName,
            'chart_color' => '#112233',
            'chart_type' => $chartType,
            'trigger' => true,
        ]);
    }
}
