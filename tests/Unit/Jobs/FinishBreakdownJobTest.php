<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Data\PayloadData;
use App\Enums\ProductionStatus;
use App\Events\ProductionSummaryNotification;
use App\Jobs\BreakdownJudgeJob;
use App\Jobs\FinishBreakdownJob;
use App\Models\DefectiveProduction;
use App\Models\Production;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use App\Repositories\DefectiveProductionRepository;
use App\Repositories\PayloadRepository;
use App\Repositories\ProductionHistoryRepository;
use App\Repositories\ProductionLineRepository;
use App\Repositories\ProductionRepository;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class FinishBreakdownJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_handleは通常ラインでカウント更新し通知とチョコ停判定ジョブを投入する(): void
    {
        Queue::fake();
        Event::fake([ProductionSummaryNotification::class]);
        $this->mockTransactionWithAfterCommit();

        $date = Carbon::parse('2026-04-13 13:00:00');
        $history = $this->makeHistory();
        $line = $this->makeLine(701, false, true);
        $history->setRelation('productionLines', new EloquentCollection([$line]));

        $baseLine = $this->makeLine(701, false, true);
        $baseLine->setRelation('productionHistory', $history);

        $payloadData = $this->makePayloadData(defectiveLineId: 0, indicator: true);

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository
            ->shouldReceive('find')
            ->once()
            ->with(701, ['productionHistory.productionLines'])
            ->andReturn($baseLine);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository
            ->shouldReceive('updatePayload')
            ->once()
            ->with($line, Mockery::type('callable'))
            ->andReturnUsing(function (ProductionLine $targetLine, callable $callback) use ($payloadData): PayloadData {
                $callback($payloadData);
                return $payloadData;
            });

        $savedProduction = $this->makeProduction(701, Carbon::parse('2026-04-13 13:00:10'));

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository
            ->shouldReceive('save')
            ->once()
            ->with(701, Mockery::type(PayloadData::class))
            ->andReturn($savedProduction);

        $defectiveProductionRepository = Mockery::mock(DefectiveProductionRepository::class);
        $defectiveProductionRepository->shouldNotReceive('save');

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository->shouldReceive('makeProductionSummary')
            ->once()
            ->andReturn($this->makeProductionSummaryData());

        $job = new FinishBreakdownJob(701, 15, $date);
        $job->handle(
            $payloadRepository,
            $productionLineRepository,
            $productionRepository,
            $defectiveProductionRepository,
            $productionHistoryRepository,
        );

        $this->assertSame(15, $payloadData->count);
        Event::assertDispatched(ProductionSummaryNotification::class);
        Queue::assertPushed(BreakdownJudgeJob::class, function (BreakdownJudgeJob $job) use ($savedProduction): bool {
            return $job->delay instanceof Carbon
                && $job->delay->equalTo($savedProduction->at->copy()->addMilliseconds(500))
                && $job->afterCommit === true;
        });
    }

    public function test_handleは不良品ラインで不良品保存し親ラインに反映する(): void
    {
        Queue::fake();
        Event::fake([ProductionSummaryNotification::class]);
        $this->mockTransactionWithAfterCommit();

        $date = Carbon::parse('2026-04-13 13:10:00');
        $history = $this->makeHistory();
        $parentLine = $this->makeLine(702, false, true);
        $history->setRelation('productionLines', new EloquentCollection([$parentLine]));

        $defectiveLine = $this->makeLine(703, true, false);
        $defectiveLine->parent_id = 702;
        $defectiveLine->setRelation('productionHistory', $history);

        $payloadData = $this->makePayloadData(defectiveLineId: 703, indicator: true);

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository
            ->shouldReceive('find')
            ->once()
            ->with(703, ['productionHistory.productionLines'])
            ->andReturn($defectiveLine);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository
            ->shouldReceive('updatePayload')
            ->once()
            ->with($parentLine, Mockery::type('callable'))
            ->andReturnUsing(function (ProductionLine $targetLine, callable $callback) use ($payloadData): PayloadData {
                $callback($payloadData);
                return $payloadData;
            });

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository
            ->shouldReceive('save')
            ->once()
            ->with(702, Mockery::type(PayloadData::class))
            ->andReturn($this->makeProduction(702, Carbon::parse('2026-04-13 13:10:10')));

        $defectiveProductionRepository = Mockery::mock(DefectiveProductionRepository::class);
        $defectiveProductionRepository
            ->shouldReceive('save')
            ->once()
            ->with(703, 4, $date)
            ->andReturn(new DefectiveProduction());

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository->shouldReceive('makeProductionSummary')
            ->once()
            ->andReturn($this->makeProductionSummaryData());

        $job = new FinishBreakdownJob(703, 4, $date);
        $job->handle(
            $payloadRepository,
            $productionLineRepository,
            $productionRepository,
            $defectiveProductionRepository,
            $productionHistoryRepository,
        );

        $this->assertSame(4, $payloadData->defectiveCount());
        Event::assertDispatched(ProductionSummaryNotification::class);
        Queue::assertPushed(BreakdownJudgeJob::class, 1);
    }

    public function test_handleは対象生産ラインが無い場合に例外を送出する(): void
    {
        $this->mockTransactionWithAfterCommit();

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository
            ->shouldReceive('find')
            ->once()
            ->with(999, ['productionHistory.productionLines'])
            ->andReturn(null);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository->shouldNotReceive('updatePayload');

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository->shouldNotReceive('save');

        $defectiveProductionRepository = Mockery::mock(DefectiveProductionRepository::class);
        $defectiveProductionRepository->shouldNotReceive('save');

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);

        $job = new FinishBreakdownJob(999, 1, Carbon::parse('2026-04-13 13:20:00'));

        $this->expectException(ModelNotFoundException::class);

        $job->handle(
            $payloadRepository,
            $productionLineRepository,
            $productionRepository,
            $defectiveProductionRepository,
            $productionHistoryRepository,
        );
    }

    private function makeHistory(): ProductionHistory
    {
        $history = new ProductionHistory();
        $history->production_history_id = 200;
        $history->process_id = 1;
        $history->process_name = '工程A';
        $history->part_number_id = 2;
        $history->part_number_name = 'PN-001';
        $history->goal = 100;
        $history->start = Carbon::parse('2026-04-13 12:00:00');
        $history->cycle_time = 1.0;
        $history->over_time = 0.5;
        $history->status = ProductionStatus::RUNNING();

        return $history;
    }

    private function makeLine(int $lineId, bool $defective, bool $indicator): ProductionLine
    {
        $line = new ProductionLine();
        $line->production_line_id = $lineId;
        $line->defective = $defective;
        $line->indicator = $indicator;

        return $line;
    }

    private function makePayloadData(int $defectiveLineId, bool $indicator): PayloadData
    {
        $defectiveCounts = [];
        if ($defectiveLineId > 0) {
            $defectiveCounts[$defectiveLineId] = 0;
        }

        return new PayloadData(
            lineId: 1,
            defectiveCounts: $defectiveCounts,
            start: '2026-04-13 12:50:00.000000',
            countSwitch: true,
            cycleTimeMs: 1000,
            overTimeMs: 500,
            plannedOutages: [],
            changeovers: [],
            indicator: $indicator,
        );
    }

    private function makeProduction(int $lineId, Carbon $at): Production
    {
        $production = new Production();
        $production->production_id = 900;
        $production->production_line_id = $lineId;
        $production->count = 1;
        $production->status = ProductionStatus::RUNNING();
        $production->at = $at;

        return $production;
    }

    private function mockTransactionWithAfterCommit(): void
    {
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(static function (callable $callback): void {
                $callback();
            });

        DB::shouldReceive('afterCommit')
            ->zeroOrMoreTimes()
            ->andReturnUsing(static function (callable $callback): void {
                $callback();
            });
    }
}
