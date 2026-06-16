<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Data\PayloadData;
use App\Enums\ProductionStatus;
use App\Events\ProductionSummaryNotification;
use App\Jobs\BreakdownJudgeJob;
use App\Models\Payload;
use App\Models\Production;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use App\Repositories\PayloadRepository;
use App\Repositories\ProductionHistoryRepository;
use App\Repositories\ProductionLineRepository;
use App\Repositories\ProductionRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class BreakdownJudgeJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_handleは稼働中以外のステータスでは何もしない(): void
    {
        $this->mockTransaction();
        Event::fake([ProductionSummaryNotification::class]);

        $production = $this->makeProduction(11);
        $history = $this->makeHistory(ProductionStatus::RUNNING());
        $productionLine = $this->makeProductionLine(11, $history);

        $payloadData = $this->makePayloadData(indicator: true);
        $payloadData->isComplete = true; // status() を COMPLETE にする

        $payloadRepository = $this->mockPayloadRepositoryForRead($productionLine, $payloadData);

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository->shouldNotReceive('updateStatus');

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository->shouldNotReceive('judgeBreakdown');
        $productionRepository->shouldNotReceive('save');

        $job = new BreakdownJudgeJob($production, Carbon::parse('2026-04-13 09:10:00'));
        $job->handle(
            $payloadRepository,
            $productionHistoryRepository,
            Mockery::mock(ProductionLineRepository::class, function ($mock) use ($productionLine): void {
                $mock->shouldReceive('find')
                    ->once()
                    ->with(11, ['productionHistory.productionLines'])
                    ->andReturn($productionLine);
            }),
            $productionRepository,
        );

        Event::assertNotDispatched(ProductionSummaryNotification::class);
    }

    public function test_handleは指標ラインでなければ何もしない(): void
    {
        $this->mockTransaction();
        Event::fake([ProductionSummaryNotification::class]);

        $production = $this->makeProduction(12);
        $history = $this->makeHistory(ProductionStatus::RUNNING());
        $productionLine = $this->makeProductionLine(12, $history);

        $payloadData = $this->makePayloadData(indicator: false);

        $payloadRepository = $this->mockPayloadRepositoryForRead($productionLine, $payloadData);

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository->shouldNotReceive('updateStatus');

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository->shouldNotReceive('judgeBreakdown');
        $productionRepository->shouldNotReceive('save');

        $job = new BreakdownJudgeJob($production, Carbon::parse('2026-04-13 09:20:00'));
        $job->handle(
            $payloadRepository,
            $productionHistoryRepository,
            Mockery::mock(ProductionLineRepository::class, function ($mock) use ($productionLine): void {
                $mock->shouldReceive('find')->once()->andReturn($productionLine);
            }),
            $productionRepository,
        );

        Event::assertNotDispatched(ProductionSummaryNotification::class);
    }

    public function test_handleはチョコ停判定がfalseなら更新しない(): void
    {
        $this->mockTransaction();
        Event::fake([ProductionSummaryNotification::class]);

        $production = $this->makeProduction(13);
        $history = $this->makeHistory(ProductionStatus::RUNNING());
        $productionLine = $this->makeProductionLine(13, $history);

        $payloadData = $this->makePayloadData(indicator: true);

        $payloadRepository = $this->mockPayloadRepositoryForRead($productionLine, $payloadData);
        $payloadRepository->shouldNotReceive('updatePayload');

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository->shouldNotReceive('updateStatus');

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository
            ->shouldReceive('judgeBreakdown')
            ->once()
            ->with($production, Mockery::type(Carbon::class))
            ->andReturn(false);
        $productionRepository->shouldNotReceive('save');

        $job = new BreakdownJudgeJob($production, Carbon::parse('2026-04-13 09:30:00'));
        $job->handle(
            $payloadRepository,
            $productionHistoryRepository,
            Mockery::mock(ProductionLineRepository::class, function ($mock) use ($productionLine): void {
                $mock->shouldReceive('find')->once()->andReturn($productionLine);
            }),
            $productionRepository,
        );

        Event::assertNotDispatched(ProductionSummaryNotification::class);
    }

    public function test_handleはチョコ停時に履歴更新と保存と通知を行う(): void
    {
        $this->mockTransaction();
        Event::fake([ProductionSummaryNotification::class]);

        $production = $this->makeProduction(14);
        $history = $this->makeHistory(ProductionStatus::RUNNING());

        $indicatorLine = new ProductionLine();
        $indicatorLine->production_line_id = 114;
        $history->setRelation('indicatorLine', $indicatorLine);

        $productionLine = $this->makeProductionLine(14, $history);

        $payloadData = $this->makePayloadData(indicator: true);

        $payloadRepository = $this->mockPayloadRepositoryForRead($productionLine, $payloadData);
        $payloadRepository
            ->shouldReceive('updatePayload')
            ->once()
            ->with($indicatorLine, Mockery::type('callable'))
            ->andReturnUsing(function (ProductionLine $line, callable $callback) use ($payloadData): PayloadData {
                $callback($payloadData);
                return $payloadData;
            });

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository
            ->shouldReceive('updateStatus')
            ->once()
            ->with(
                $history,
                Mockery::on(fn(ProductionStatus $status): bool => $status->is(ProductionStatus::BREAKDOWN()))
            );

        $productionHistoryRepository->shouldReceive('makeProductionSummary')
            ->once()
            ->andReturn($this->makeProductionSummaryData());

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository
            ->shouldReceive('judgeBreakdown')
            ->once()
            ->andReturn(true);
        $productionRepository
            ->shouldReceive('save')
            ->once()
            ->with(114, Mockery::type(PayloadData::class))
            ->andReturn(new Production());

        $job = new BreakdownJudgeJob($production, Carbon::parse('2026-04-13 09:40:00'));
        $job->handle(
            $payloadRepository,
            $productionHistoryRepository,
            Mockery::mock(ProductionLineRepository::class, function ($mock) use ($productionLine): void {
                $mock->shouldReceive('find')->once()->andReturn($productionLine);
            }),
            $productionRepository,
        );

        Event::assertDispatched(ProductionSummaryNotification::class);
    }

    public function test_handleは履歴ステータスがRUNNING以外ならステータス更新を行わない(): void
    {
        $this->mockTransaction();
        Event::fake([ProductionSummaryNotification::class]);

        $production = $this->makeProduction(15);
        $history = $this->makeHistory(ProductionStatus::COMPLETE());

        $indicatorLine = new ProductionLine();
        $indicatorLine->production_line_id = 115;
        $history->setRelation('indicatorLine', $indicatorLine);

        $productionLine = $this->makeProductionLine(15, $history);

        $payloadData = $this->makePayloadData(indicator: true);

        $payloadRepository = $this->mockPayloadRepositoryForRead($productionLine, $payloadData);
        $payloadRepository
            ->shouldReceive('updatePayload')
            ->once()
            ->with($indicatorLine, Mockery::type('callable'))
            ->andReturn($payloadData);

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository->shouldNotReceive('updateStatus');

        $productionHistoryRepository->shouldReceive('makeProductionSummary')
            ->once()
            ->andReturn($this->makeProductionSummaryData());

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository
            ->shouldReceive('judgeBreakdown')
            ->once()
            ->andReturn(true);
        $productionRepository
            ->shouldReceive('save')
            ->once()
            ->with(115, Mockery::type(PayloadData::class))
            ->andReturn(new Production());

        $job = new BreakdownJudgeJob($production, Carbon::parse('2026-04-13 09:50:00'));
        $job->handle(
            $payloadRepository,
            $productionHistoryRepository,
            Mockery::mock(ProductionLineRepository::class, function ($mock) use ($productionLine): void {
                $mock->shouldReceive('find')->once()->andReturn($productionLine);
            }),
            $productionRepository,
        );

        Event::assertDispatched(ProductionSummaryNotification::class);
    }

    public function test_delayedDispatchは指定ミリ秒後でジョブを遅延投入する(): void
    {
        Queue::fake();

        $production = $this->makeProduction(20);
        $production->at = Carbon::parse('2026-04-13 10:00:00');

        BreakdownJudgeJob::delayedDispatch(1500, $production);

        Queue::assertPushed(BreakdownJudgeJob::class, function (BreakdownJudgeJob $job): bool {
            return $job->delay instanceof Carbon
                && $job->delay->equalTo(Carbon::parse('2026-04-13 10:00:01.500000'))
                && $job->afterCommit === true;
        });
    }

    private function mockPayloadRepositoryForRead(ProductionLine $productionLine, PayloadData $payloadData)
    {
        $payload = Mockery::mock(Payload::class);
        $payload
            ->shouldReceive('getPayloadData')
            ->once()
            ->andReturn($payloadData);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository
            ->shouldReceive('getPayload')
            ->once()
            ->with($productionLine)
            ->andReturn($payload);

        return $payloadRepository;
    }

    private function makePayloadData(bool $indicator): PayloadData
    {
        return new PayloadData(
            lineId: 1,
            defectiveCounts: [],
            start: '2026-04-13 09:00:00.000000',
            countSwitch: true,
            cycleTimeMs: 1000,
            overTimeMs: 500,
            plannedOutages: [],
            changeovers: [],
            indicator: $indicator,
        );
    }

    private function makeProduction(int $lineId): Production
    {
        $production = new Production();
        $production->production_id = 999;
        $production->production_line_id = $lineId;
        $production->count = 10;
        $production->status = ProductionStatus::RUNNING();
        $production->at = Carbon::parse('2026-04-13 09:00:00');

        return $production;
    }

    private function makeHistory(ProductionStatus $status): ProductionHistory
    {
        $history = new ProductionHistory();
        $history->production_history_id = 500;
        $history->process_id = 1;
        $history->process_name = '工程A';
        $history->part_number_id = 2;
        $history->part_number_name = 'PN-001';
        $history->goal = 100;
        $history->start = Carbon::parse('2026-04-13 08:30:00');
        $history->status = $status;

        return $history;
    }

    private function makeProductionLine(int $lineId, ProductionHistory $history): ProductionLine
    {
        $line = new ProductionLine();
        $line->production_line_id = $lineId;
        $line->setRelation('productionHistory', $history);

        return $line;
    }

    private function mockTransaction(): void
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
