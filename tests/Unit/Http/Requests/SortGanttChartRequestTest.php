<?php

namespace Tests\Unit\Http\Requests;

use App\Enums\GanttChartType;
use App\Http\Requests\SortGanttChartRequest;
use App\Models\GanttChart;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SortGanttChartRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorizeは管理者ユーザーを許可する(): void
    {
        $user = User::factory()->create(['role' => 5]);
        $this->actingAs($user);

        $request = new SortGanttChartRequest();

        $this->assertTrue($request->authorize());
    }

    public function test_authorizeは一般ユーザーを拒否する(): void
    {
        $user = User::factory()->create(['role' => 10]);
        $this->actingAs($user);

        $request = new SortGanttChartRequest();

        $this->assertFalse($request->authorize());
    }

    public function test_rulesは実在するID配列を許可する(): void
    {
        $ganttChart = $this->createGanttChart();
        $request = new SortGanttChartRequest();

        $validator = Validator::make([
            'order' => [$ganttChart->gantt_chart_id],
        ], $request->rules());

        $this->assertFalse($validator->fails());
    }

    public function test_rulesはorderが未指定の場合に失敗する(): void
    {
        $request = new SortGanttChartRequest();

        $validator = Validator::make([], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('order', $validator->errors()->toArray());
    }

    public function test_rulesは存在しないIDを拒否する(): void
    {
        $request = new SortGanttChartRequest();

        $validator = Validator::make([
            'order' => [999999],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('order.0', $validator->errors()->toArray());
    }

    public function test_rulesは重複IDを拒否する(): void
    {
        $ganttChart = $this->createGanttChart();
        $request = new SortGanttChartRequest();

        $validator = Validator::make([
            'order' => [$ganttChart->gantt_chart_id, $ganttChart->gantt_chart_id],
        ], $request->rules());

        $this->assertTrue($validator->fails());
        $this->assertArrayHasKey('order.1', $validator->errors()->toArray());
    }

    private function createGanttChart(): GanttChart
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();

        return GanttChart::factory()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 1,
            'chart_name' => 'sort-test-chart',
            'chart_color' => '#123456',
            'chart_type' => GanttChartType::WORK(),
            'trigger' => true,
        ]);
    }
}
