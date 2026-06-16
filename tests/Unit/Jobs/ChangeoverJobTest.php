<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Data\PayloadData;
use App\Enums\ProductionStatus;
use App\Events\ProductionSummaryNotification;
use App\Jobs\BreakdownJudgeJob;
use App\Jobs\ChangeoverJob;
use App\Jobs\PlanCountJob;
use App\Models\Production;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use App\Repositories\PayloadRepository;
use App\Repositories\ProductionHistoryRepository;
use App\Repositories\ProductionRepository;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ChangeoverJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_handleは指標ラインで通知しPlanCountJobをafterCommit投入する(): void
    {
        Queue::fake();
        Event::fake([ProductionSummaryNotification::class]);
        $this->mockTransactionWithAfterCommit();

        $date = Carbon::parse('2026-04-13 11:00:00');
        $history = $this->makeHistory(cycleTime: 2.0, overTime: 0.5);
        $line = $this->makeLine(401, true);
        $history->setRelation('productionLines', new EloquentCollection([$line]));

        $payloadData = $this->makePayloadData(true);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository
            ->shouldReceive('updatePayload')
            ->once()
            ->with($line, Mockery::type('callable'))
            ->andReturnUsing(function (ProductionLine $productionLine, callable $callback) use ($payloadData): PayloadData {
                $callback($payloadData);
                return $payloadData;
            });

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository
            ->shouldReceive('find')
            ->once()
            ->with(101, ['productionLines.payload'])
            ->andReturn($history);

        $productionHistoryRepository->shouldReceive('makeProductionSummary')
            ->once()
            ->andReturn($this->makeProductionSummaryData());

        $savedProduction = $this->makeSavedProduction(401, Carbon::parse('2026-04-13 11:00:10'));

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository
            ->shouldReceive('save')
            ->once()
            ->with(401, Mockery::type(PayloadData::class))
            ->andReturn($savedProduction);

        $job = new ChangeoverJob(101, $date, true);
        $job->handle($payloadRepository, $productionHistoryRepository, $productionRepository);

        Event::assertDispatched(ProductionSummaryNotification::class);

        Queue::assertPushed(PlanCountJob::class, function (PlanCountJob $job): bool {
            return $job->delay instanceof Carbon
                && $job->delay->equalTo(Carbon::parse('2026-04-13 11:00:02.000000'))
                && $job->afterCommit === true;
        });
        Queue::assertNotPushed(BreakdownJudgeJob::class);
    }

    public function test_handleは非指標ラインでは通知とBreakdownJudgeJobを投入しない(): void
    {
        Queue::fake();
        Event::fake([ProductionSummaryNotification::class]);
        $this->mockTransactionWithAfterCommit();

        $date = Carbon::parse('2026-04-13 11:10:00');
        $history = $this->makeHistory(cycleTime: 2.0, overTime: 0.5);
        $line = $this->makeLine(402, false);
        $history->setRelation('productionLines', new EloquentCollection([$line]));

        $payloadData = $this->makePayloadData(false);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository
            ->shouldReceive('updatePayload')
            ->once()
            ->with($line, Mockery::type('callable'))
            ->andReturnUsing(function (ProductionLine $productionLine, callable $callback) use ($payloadData): PayloadData {
                $callback($payloadData);
                return $payloadData;
            });

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($history);

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository
            ->shouldReceive('save')
            ->once()
            ->with(402, Mockery::type(PayloadData::class))
            ->andReturn($this->makeSavedProduction(402, Carbon::parse('2026-04-13 11:10:10')));

        $job = new ChangeoverJob(102, $date, false);
        $job->handle($payloadRepository, $productionHistoryRepository, $productionRepository);

        Event::assertNotDispatched(ProductionSummaryNotification::class);
        Queue::assertPushed(PlanCountJob::class, 1);
        Queue::assertNotPushed(BreakdownJudgeJob::class);
    }

    public function test_handleは段取り替え終了時に指標ラインへBreakdownJudgeJobを投入する(): void
    {
        Queue::fake();
        Event::fake([ProductionSummaryNotification::class]);
        $this->mockTransactionWithAfterCommit();

        $date = Carbon::parse('2026-04-13 11:20:00');
        $history = $this->makeHistory(cycleTime: 2.0, overTime: 0.5);
        $line = $this->makeLine(403, true);
        $history->setRelation('productionLines', new EloquentCollection([$line]));

        $payloadData = $this->makePayloadData(true);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository
            ->shouldReceive('updatePayload')
            ->once()
            ->with($line, Mockery::type('callable'))
            ->andReturnUsing(function (ProductionLine $productionLine, callable $callback) use ($payloadData): PayloadData {
                $callback($payloadData);
                return $payloadData;
            });

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($history);

        $productionHistoryRepository->shouldReceive('makeProductionSummary')
            ->once()
            ->andReturn($this->makeProductionSummaryData());

        $savedAt = Carbon::parse('2026-04-13 11:20:30');
        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository
            ->shouldReceive('save')
            ->once()
            ->andReturn($this->makeSavedProduction(403, $savedAt));

        $job = new ChangeoverJob(103, $date, false);
        $job->handle($payloadRepository, $productionHistoryRepository, $productionRepository);

        Queue::assertPushed(BreakdownJudgeJob::class, function (BreakdownJudgeJob $job) use ($savedAt): bool {
            return $job->delay instanceof Carbon
                && $job->delay->equalTo($savedAt->copy()->addMilliseconds(500))
                && $job->afterCommit === true;
        });
    }

    public function test_handleは履歴が見つからない場合に例外を送出する(): void
    {
        $this->mockTransactionWithAfterCommit();

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository
            ->shouldReceive('find')
            ->once()
            ->with(104, ['productionLines.payload'])
            ->andReturn(null);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository->shouldNotReceive('updatePayload');

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository->shouldNotReceive('save');

        $job = new ChangeoverJob(104, Carbon::parse('2026-04-13 11:30:00'), true);

        $this->expectException(ModelNotFoundException::class);

        $job->handle($payloadRepository, $productionHistoryRepository, $productionRepository);
    }

    private function makeHistory(float $cycleTime, float $overTime): ProductionHistory
    {
        $history = new ProductionHistory();
        $history->production_history_id = 100;
        $history->process_id = 1;
        $history->process_name = '工程A';
        $history->part_number_id = 2;
        $history->part_number_name = 'PN-001';
        $history->goal = 100;
        $history->start = Carbon::parse('2026-04-13 10:00:00');
        $history->cycle_time = $cycleTime;
        $history->over_time = $overTime;
        $history->status = ProductionStatus::RUNNING();

        return $history;
    }

    private function makeLine(int $lineId, bool $indicator): ProductionLine
    {
        $line = new ProductionLine();
        $line->production_line_id = $lineId;
        $line->indicator = $indicator;

        return $line;
    }

    private function makePayloadData(bool $indicator): PayloadData
    {
        return new PayloadData(
            lineId: 1,
            defectiveCounts: [],
            start: '2026-04-13 10:00:00.000000',
            countSwitch: true,
            cycleTimeMs: 1000,
            overTimeMs: 500,
            plannedOutages: [],
            changeovers: [],
            indicator: $indicator,
        );
    }

    private function makeSavedProduction(int $lineId, Carbon $at): Production
    {
        $production = new Production();
        $production->production_id = 501;
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
