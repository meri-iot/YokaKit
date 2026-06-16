<?php

namespace Tests\Feature\Http\Controllers;

use App\Enums\GanttChartType;
use App\Models\GanttChart;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\User;
use App\Services\GanttChartService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery\MockInterface;
use Tests\Feature\TestCommon;
use Tests\TestCase;

class GanttChartControllerTest extends TestCase
{
    use RefreshDatabase;
    use TestCommon;

    private User $adminUser;
    private User $normalUser;
    private Process $process;

    protected function setUp(): void
    {
        parent::setUp();
        $this->adminUser = User::factory()->create(['role' => 5]);
        $this->normalUser = User::factory()->create();
        $this->process = Process::factory()->create();
    }

    protected function tearDown(): void
    {
        \Mockery::close();
        parent::tearDown();
    }

    public function test_indexページは認証ユーザーが表示できる(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('gantt-chart.index', $this->process));
        $response->assertStatus(200);
    }

    public function test_indexページ未認証ユーザーはリダイレクト(): void
    {
        $response = $this->get(route('gantt-chart.index', $this->process));
        $response->assertRedirect(route('login'));
    }

    public function test_createコマンド管理者のみアクセス可能(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('gantt-chart.create', $this->process));
        $response->assertStatus(403);
    }

    public function test_create管理者はアクセス可能(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('gantt-chart.create', $this->process));
        $response->assertStatus(200);
    }

    public function test_store管理者のみ実行可能(): void
    {
        $response = $this->actingAs($this->normalUser)->post(
            route('gantt-chart.store', $this->process),
            [
                'raspberry_pi_id' => 1,
                'pin_number' => 1,
                'chart_name' => 'test',
                'chart_color' => '#000000',
                'chart_type' => GanttChartType::BASE(),
            ]
        );
        $response->assertStatus(403);
    }

    public function test_storeサービス成功時はsuccessトーストで遷移(): void
    {
        $this->mock(GanttChartService::class, function (MockInterface $mock) {
            $mock->shouldReceive('store')->andReturn(true);
        });

        $raspberryPi = RaspberryPi::factory()->create();
        $response = $this->actingAs($this->adminUser)->post(
            route('gantt-chart.store', $this->process),
            [
                'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
                'pin_number' => 1,
                'chart_name' => 'test-chart',
                'chart_color' => '#000000',
                'chart_type' => GanttChartType::BASE(),
            ]
        );
        $response->assertRedirect(route('process.show', ['process' => $this->process, 'tab' => 'gantt-chart']));
        $response->assertSessionHas('toast_success');
    }

    public function test_storeサービス失敗時はdangerトーストで遷移(): void
    {
        $this->mock(GanttChartService::class, function (MockInterface $mock) {
            $mock->shouldReceive('store')->andReturn(false);
        });

        $raspberryPi = RaspberryPi::factory()->create();
        $response = $this->actingAs($this->adminUser)->post(
            route('gantt-chart.store', $this->process),
            [
                'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
                'pin_number' => 1,
                'chart_name' => 'test-chart',
                'chart_color' => '#000000',
                'chart_type' => GanttChartType::BASE(),
            ]
        );
        $response->assertRedirect(route('process.show', ['process' => $this->process, 'tab' => 'gantt-chart']));
        $response->assertSessionHas('toast_danger');
    }

    public function test_edit管理者のみアクセス可能(): void
    {
        $raspberryPi = RaspberryPi::factory()->create();
        $ganttChart = GanttChart::factory()->create([
            'process_id' => $this->process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 1,
            'chart_name' => 'test',
            'chart_color' => '#000000',
            'chart_type' => GanttChartType::WORK(),
            'trigger' => true,
        ]);
        $response = $this->actingAs($this->normalUser)->get(
            route('gantt-chart.edit', [$this->process, $ganttChart])
        );
        $response->assertStatus(403);
    }

    public function test_edit管理者はアクセス可能(): void
    {
        $raspberryPi = RaspberryPi::factory()->create();
        $ganttChart = GanttChart::factory()->create([
            'process_id' => $this->process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 1,
            'chart_name' => 'test',
            'chart_color' => '#000000',
            'chart_type' => GanttChartType::WORK(),
            'trigger' => true,
        ]);
        $response = $this->actingAs($this->adminUser)->get(
            route('gantt-chart.edit', [$this->process, $ganttChart])
        );
        $response->assertStatus(200);
    }

    public function test_update管理者のみ実行可能(): void
    {
        $raspberryPi = RaspberryPi::factory()->create();
        $ganttChart = GanttChart::factory()->create([
            'process_id' => $this->process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 1,
            'chart_name' => 'test',
            'chart_color' => '#000000',
            'chart_type' => GanttChartType::WORK(),
            'trigger' => true,
        ]);
        $response = $this->actingAs($this->normalUser)->put(
            route('gantt-chart.update', [$this->process, $ganttChart]),
            [
                'raspberry_pi_id' => $ganttChart->raspberry_pi_id,
                'pin_number' => $ganttChart->pin_number,
                'chart_name' => 'updated',
                'chart_color' => '#000000',
                'chart_type' => GanttChartType::BASE(),
            ]
        );
        $response->assertStatus(403);
    }

    public function test_update管理者のsuccessトーストテスト(): void
    {
        $this->mock(GanttChartService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->andReturn(true);
        });

        $raspberryPi = RaspberryPi::factory()->create();
        $ganttChart = GanttChart::factory()->create([
            'process_id' => $this->process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 1,
            'chart_name' => 'test',
            'chart_color' => '#000000',
            'chart_type' => GanttChartType::WORK(),
            'trigger' => true,
        ]);
        $response = $this->actingAs($this->adminUser)->put(
            route('gantt-chart.update', [$this->process, $ganttChart]),
            [
                'raspberry_pi_id' => $ganttChart->raspberry_pi_id,
                'pin_number' => $ganttChart->pin_number,
                'chart_name' => 'updated',
                'chart_color' => '#000000',
                'chart_type' => GanttChartType::BASE(),
            ]
        );
        $response->assertRedirect(route('process.show', ['process' => $this->process, 'tab' => 'gantt-chart']));
        $response->assertSessionHas('toast_success');
    }

    public function test_updateサービス失敗時はdangerトーストで遷移(): void
    {
        $this->mock(GanttChartService::class, function (MockInterface $mock) {
            $mock->shouldReceive('update')->andReturn(false);
        });

        $raspberryPi = RaspberryPi::factory()->create();
        $ganttChart = GanttChart::factory()->create([
            'process_id' => $this->process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 1,
            'chart_name' => 'test',
            'chart_color' => '#000000',
            'chart_type' => GanttChartType::WORK(),
            'trigger' => true,
        ]);
        $response = $this->actingAs($this->adminUser)->put(
            route('gantt-chart.update', [$this->process, $ganttChart]),
            [
                'raspberry_pi_id' => $ganttChart->raspberry_pi_id,
                'pin_number' => $ganttChart->pin_number,
                'chart_name' => 'updated',
                'chart_color' => '#000000',
                'chart_type' => GanttChartType::BASE(),
            ]
        );
        $response->assertRedirect(route('process.show', ['process' => $this->process, 'tab' => 'gantt-chart']));
        $response->assertSessionHas('toast_danger');
    }

    public function test_destroy管理者のみ実行可能(): void
    {
        $raspberryPi = RaspberryPi::factory()->create();
        $ganttChart = GanttChart::factory()->create([
            'process_id' => $this->process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 1,
            'chart_name' => 'test',
            'chart_color' => '#000000',
            'chart_type' => GanttChartType::WORK(),
            'trigger' => true,
        ]);
        $response = $this->actingAs($this->normalUser)->delete(
            route('gantt-chart.destroy', [$this->process, $ganttChart])
        );
        $response->assertStatus(403);
    }

    public function test_destroy管理者は削除可能(): void
    {
        $this->mock(GanttChartService::class, function (MockInterface $mock) {
            $mock->shouldReceive('destroy')->andReturn(true);
        });

        $raspberryPi = RaspberryPi::factory()->create();
        $ganttChart = GanttChart::factory()->create([
            'process_id' => $this->process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 1,
            'chart_name' => 'test',
            'chart_color' => '#000000',
            'chart_type' => GanttChartType::WORK(),
            'trigger' => true,
        ]);
        $response = $this->actingAs($this->adminUser)->delete(
            route('gantt-chart.destroy', [$this->process, $ganttChart])
        );
        $response->assertRedirect(route('process.show', ['process' => $this->process, 'tab' => 'gantt-chart']));
        $response->assertSessionHas('toast_success');
    }

    public function test_sorting管理者のみアクセス可能(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('gantt-chart.sorting', $this->process));
        $response->assertStatus(403);
    }

    public function test_sorting管理者はアクセス可能(): void
    {
        $response = $this->actingAs($this->adminUser)->get(route('gantt-chart.sorting', $this->process));
        $response->assertStatus(200);
    }

    public function test_sort管理者のみ実行可能(): void
    {
        $response = $this->actingAs($this->normalUser)->post(
            route('gantt-chart.sort', $this->process),
            ['gantt_charts' => []]
        );
        $response->assertStatus(403);
    }

    public function test_sort管理者は実行可能(): void
    {
        $this->mock(GanttChartService::class, function (MockInterface $mock) {
            $mock->shouldReceive('sort')->andReturn(null);
        });

        $raspberryPi = RaspberryPi::factory()->create();
        $ganttChart1 = GanttChart::factory()->create([
            'process_id' => $this->process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'pin_number' => 1,
            'chart_name' => 'test1',
            'chart_color' => '#000000',
            'chart_type' => GanttChartType::WORK(),
            'trigger' => true,
        ]);

        $response = $this->actingAs($this->adminUser)->post(
            route('gantt-chart.sort', $this->process),
            ['order' => [$ganttChart1->gantt_chart_id]]
        );
        $response->assertRedirect(route('process.show', ['process' => $this->process, 'tab' => 'gantt-chart']));
        $response->assertSessionHas('toast_success');
    }

    public function test_allページは認証ユーザーが表示できる(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('gantt.index'));
        $response->assertStatus(200);
    }

    public function test_allページ未認証ユーザーはリダイレクト(): void
    {
        $response = $this->get(route('gantt.index'));
        $response->assertRedirect(route('login'));
    }

    public function test_historyページは認証ユーザーが表示できる(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('gantt-chart.history', [
            'process' => $this->process,
            'startDate' => '2026-01-01',
            'endDate' => '2026-01-01',
        ]));
        $response->assertStatus(200);
    }

    public function test_historyページは空文字の日付クエリでも表示できる(): void
    {
        $response = $this->actingAs($this->normalUser)->get(route('gantt-chart.history', [
            'process' => $this->process,
            'startDate' => '',
            'endDate' => '',
        ]));

        $response->assertStatus(200);
    }

    public function test_historyページ未認証ユーザーはリダイレクト(): void
    {
        $response = $this->get(route('gantt-chart.history', $this->process));
        $response->assertRedirect(route('login'));
    }
}
