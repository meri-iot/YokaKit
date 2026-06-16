<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Data\PayloadData;
use App\Http\Requests\UpdateAndonConfigRequest;
use App\Models\AndonConfig;
use App\Models\AndonLayout;
use App\Models\Payload;
use App\Models\Process;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use App\Models\User;
use App\Repositories\AndonConfigRepository;
use App\Repositories\AndonLayoutRepository;
use App\Repositories\ProcessRepository;
use App\Repositories\ProductionHistoryRepository;
use App\Services\AndonService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Mockery;
use Tests\TestCase;

class AndonServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    public function test_processesはproduction_summaryを付与して表示順で返す(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-09 12:00:00'));

        $withoutPayload = $this->makeProcess(1, 2, null);
        $withPayload = $this->makeProcess(2, 1, $this->makePayloadData(count: 12));

        $processRepository = Mockery::mock(ProcessRepository::class);
        $processRepository
            ->shouldReceive('all')
            ->once()
            ->with(['andonLayout', 'sensorEvents', 'productionHistory.indicatorLine.payload'])
            ->andReturn(new EloquentCollection([$withoutPayload, $withPayload]));

        $service = new AndonService(
            Mockery::mock(AndonConfigRepository::class),
            Mockery::mock(AndonLayoutRepository::class),
            $processRepository,
            new ProductionHistoryRepository(),
        );

        $actual = $service->processes();

        $this->assertSame([2, 1], $actual->pluck('process_id')->all());
        $this->assertSame(2, $actual[0]->production_summary['processId']);
        $this->assertSame('process-2', $actual[0]->production_summary['processName']);
        $this->assertSame(12, $actual[0]->production_summary['count']);
        $this->assertNull($actual[1]->getAttribute('production_summary'));
    }

    public function test_indicatorsはsummaryを付与して工程id順で返す(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-09 12:00:00'));

        $lateProcess = $this->makeProcess(20, 9, $this->makePayloadData(count: 5));
        $earlyProcess = $this->makeProcess(10, 3, $this->makePayloadData(count: 8));

        $processRepository = Mockery::mock(ProcessRepository::class);
        $processRepository
            ->shouldReceive('all')
            ->once()
            ->with(['productionHistory.indicatorLine.payload'])
            ->andReturn(new EloquentCollection([$lateProcess, $earlyProcess]));

        $service = new AndonService(
            Mockery::mock(AndonConfigRepository::class),
            Mockery::mock(AndonLayoutRepository::class),
            $processRepository,
            new ProductionHistoryRepository(),
        );

        $actual = $service->indicators();

        $this->assertSame([10, 20], $actual->pluck('process_id')->all());
        $this->assertSame(10, $actual[0]->summary['totalCount']);
        $this->assertSame(8, $actual[0]->summary['goodCount']);
        $this->assertArrayNotHasKey('jobKey', $actual[0]->summary);
    }

    public function test_andonConfigは指定ユーザーの設定を返す(): void
    {
        $user = User::factory()->create();

        $config = new AndonConfig(['user_id' => $user->id]);
        $andonConfigRepository = Mockery::mock(AndonConfigRepository::class);
        $andonConfigRepository
            ->shouldReceive('findOrCreateByUserId')
            ->once()
            ->with($user->id)
            ->andReturn($config);

        $service = new AndonService(
            $andonConfigRepository,
            Mockery::mock(AndonLayoutRepository::class),
            Mockery::mock(ProcessRepository::class),
            Mockery::mock(ProductionHistoryRepository::class),
        );

        $this->assertSame($config, $service->andonConfig($user->id));
    }

    public function test_updateは設定とレイアウトを更新する(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = UpdateAndonConfigRequest::create('/home', 'PUT', [
            'row_count' => 1,
            'column_count' => 1,
            'auto_play_speed' => 0,
            'slide_speed' => 0,
            'easing' => 'linear',
            'layouts' => [
                ['process_id' => 5, 'display' => 1],
            ],
            'item_column_count' => 1,
            'is_show_part_number' => true,
            'is_show_start' => true,
            'is_show_good_count' => true,
            'is_show_good_rate' => true,
            'is_show_defective_count' => true,
            'is_show_defective_rate' => true,
            'is_show_plan_count' => true,
            'is_show_achievement_rate' => true,
            'is_show_cycle_time' => true,
            'is_show_time_operating_rate' => true,
            'is_show_performance_operating_rate' => true,
            'is_show_overall_equipment_effectiveness' => true,
            'is_show_goal' => true,
        ]);

        $config = new AndonConfig(['user_id' => $user->id]);
        $andonConfigRepository = Mockery::mock(AndonConfigRepository::class);
        $andonConfigRepository
            ->shouldReceive('findOrCreateByUserId')
            ->once()
            ->with($user->id)
            ->andReturn($config);
        $andonConfigRepository
            ->shouldReceive('update')
            ->once()
            ->with($request, $config)
            ->andReturn(true);

        $andonLayoutRepository = Mockery::mock(AndonLayoutRepository::class);
        $andonLayoutRepository
            ->shouldReceive('updateLayouts')
            ->once()
            ->with($request->layouts, $user->id)
            ->andReturn(true);

        $service = new AndonService(
            $andonConfigRepository,
            $andonLayoutRepository,
            Mockery::mock(ProcessRepository::class),
            Mockery::mock(ProductionHistoryRepository::class),
        );

        $service->update($request, $user->id);

        $this->assertTrue(true);
    }

    public function test_updateはレイアウト更新失敗時にModelNotFoundExceptionを投げる(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $request = UpdateAndonConfigRequest::create('/home', 'PUT', [
            'layouts' => [
                ['process_id' => 5, 'display' => 1],
            ],
        ]);

        $config = new AndonConfig(['user_id' => $user->id]);
        $andonConfigRepository = Mockery::mock(AndonConfigRepository::class);
        $andonConfigRepository
            ->shouldReceive('findOrCreateByUserId')
            ->once()
            ->with($user->id)
            ->andReturn($config);
        $andonConfigRepository
            ->shouldReceive('update')
            ->once()
            ->with($request, $config)
            ->andReturn(true);

        $andonLayoutRepository = Mockery::mock(AndonLayoutRepository::class);
        $andonLayoutRepository
            ->shouldReceive('updateLayouts')
            ->once()
            ->with($request->layouts, $user->id)
            ->andReturn(false);

        $service = new AndonService(
            $andonConfigRepository,
            $andonLayoutRepository,
            Mockery::mock(ProcessRepository::class),
            Mockery::mock(ProductionHistoryRepository::class),
        );

        $this->expectException(ModelNotFoundException::class);

        $service->update($request, $user->id);
    }

    private function makeProcess(int $processId, int $order, ?PayloadData $payloadData): Process
    {
        $process = new Process([
            'process_name' => "process-{$processId}",
        ]);
        $process->process_id = $processId;
        $process->setRelation('andonLayout', new AndonLayout(['order' => $order]));

        $history = new ProductionHistory([
            'process_id' => $processId,
            'process_name' => "process-{$processId}",
            'part_number_id' => 99,
            'part_number_name' => 'PN-1',
            'goal' => 100,
            'start' => Carbon::parse('2026-04-09 08:00:00'),
        ]);
        $history->production_history_id = 500 + $processId;

        $indicatorLine = new ProductionLine([
            'indicator' => true,
        ]);

        if (!is_null($payloadData)) {
            $indicatorLine->setRelation('payload', new Payload([
                'payload' => $payloadData->toArray(),
            ]));
        }

        $history->setRelation('indicatorLine', $indicatorLine);
        $process->setRelation('productionHistory', $history);

        return $process;
    }

    private function makePayloadData(int $count): PayloadData
    {
        $payloadData = new PayloadData(
            lineId: 10,
            defectiveCounts: [11 => 2],
            start: '2026-04-09 08:00:00',
            countSwitch: true,
            cycleTimeMs: 1000,
            overTimeMs: 2000,
            plannedOutages: [],
            changeovers: [],
            indicator: true,
        );
        $payloadData->count = $count;
        $payloadData->loadingTime = 1000;
        $payloadData->operatingTime = 800;
        $payloadData->netTime = 600;

        return $payloadData;
    }
}
