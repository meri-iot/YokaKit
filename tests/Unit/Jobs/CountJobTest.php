<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Data\PayloadData;
use App\Enums\ProductionStatus;
use App\Events\ProductionSummaryNotification;
use App\Jobs\BreakdownJudgeJob;
use App\Jobs\CountJob;
use App\Jobs\FinishBreakdownJob;
use App\Jobs\FinishChangeoverJob;
use App\Models\DefectiveProduction;
use App\Models\Payload;
use App\Models\Production;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use App\Repositories\DefectiveProductionRepository;
use App\Repositories\PayloadRepository;
use App\Repositories\ProductionHistoryRepository;
use App\Repositories\ProductionLineRepository;
use App\Repositories\ProductionRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class CountJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_handleは通常ラインRUNNING時に生産保存と通知とチョコ停判定ジョブ投入を行う(): void
    {
        Queue::fake();
        Event::fake([ProductionSummaryNotification::class]);
        $this->mockTransactionWithAfterCommit();

        $date = Carbon::parse('2026-04-13 12:00:00');
        $history = $this->makeHistory();
        $line = $this->makeLine(501, false, true, $history);

        $payloadData = $this->makePayloadData();
        $payload = $this->mockPayload($payloadData);

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository
            ->shouldReceive('find')
            ->once()
            ->with(501, ['productionHistory'])
            ->andReturn($line);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository
            ->shouldReceive('getPayload')
            ->once()
            ->with($line)
            ->andReturn($payload);
        $payloadRepository
            ->shouldReceive('updatePayload')
            ->once()
            ->with($payload, Mockery::type('callable'))
            ->andReturnUsing(function (Payload $actualPayload, callable $callback) use ($payloadData): PayloadData {
                $callback($payloadData);
                return $payloadData;
            });

        $savedProduction = $this->makeProduction(501, Carbon::parse('2026-04-13 12:00:10'));

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository
            ->shouldReceive('save')
            ->once()
            ->with(501, Mockery::type(PayloadData::class))
            ->andReturn($savedProduction);

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository->shouldNotReceive('updateStatus');
        $productionHistoryRepository->shouldReceive('makeProductionSummary')
            ->once()
            ->andReturn($this->makeProductionSummaryData());

        $defectiveProductionRepository = Mockery::mock(DefectiveProductionRepository::class);
        $defectiveProductionRepository->shouldNotReceive('save');

        $job = new CountJob(501, 12, $date);
        $job->handle(
            $productionLineRepository,
            $productionHistoryRepository,
            $payloadRepository,
            $productionRepository,
            $defectiveProductionRepository,
        );

        Event::assertDispatched(ProductionSummaryNotification::class);
        Queue::assertPushed(BreakdownJudgeJob::class, 1);
    }

    public function test_handleは不良品ラインRUNNING時に不良品保存を行いチョコ停判定ジョブは投入しない(): void
    {
        Queue::fake();
        Event::fake([ProductionSummaryNotification::class]);
        $this->mockTransactionWithAfterCommit();

        $date = Carbon::parse('2026-04-13 12:10:00');
        $history = $this->makeHistory();
        $line = $this->makeLine(502, true, true, $history);

        $payloadData = $this->makePayloadData();
        $payload = $this->mockPayload($payloadData);

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository
            ->shouldReceive('find')
            ->once()
            ->with(502, ['productionHistory'])
            ->andReturn($line);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository
            ->shouldReceive('getPayload')
            ->once()
            ->andReturn($payload);
        $payloadRepository
            ->shouldReceive('updatePayload')
            ->once()
            ->with($payload, Mockery::type('callable'))
            ->andReturnUsing(function (Payload $actualPayload, callable $callback) use ($payloadData): PayloadData {
                $callback($payloadData);
                return $payloadData;
            });

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository
            ->shouldReceive('save')
            ->once()
            ->with(502, Mockery::type(PayloadData::class))
            ->andReturn($this->makeProduction(502, Carbon::parse('2026-04-13 12:10:20')));

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository->shouldNotReceive('updateStatus');
        $productionHistoryRepository->shouldReceive('makeProductionSummary')
            ->once()
            ->andReturn($this->makeProductionSummaryData());

        $defectiveProductionRepository = Mockery::mock(DefectiveProductionRepository::class);
        $defectiveProductionRepository
            ->shouldReceive('save')
            ->once()
            ->with(502, 7, $date)
            ->andReturn(new DefectiveProduction());

        $job = new CountJob(502, 7, $date);
        $job->handle(
            $productionLineRepository,
            $productionHistoryRepository,
            $payloadRepository,
            $productionRepository,
            $defectiveProductionRepository,
        );

        Event::assertDispatched(ProductionSummaryNotification::class);
        Queue::assertNotPushed(BreakdownJudgeJob::class);
    }

    public function test_handleはCHANGEOVER時にステータス更新とFinishChangeoverJob同期実行を行う(): void
    {
        Bus::fake();
        $this->mockTransactionWithAfterCommit();

        $date = Carbon::parse('2026-04-13 12:20:00');
        $history = $this->makeHistory();
        $line = $this->makeLine(503, false, true, $history);

        $payloadData = $this->makePayloadData();
        $payloadData->addChangeover($date, true);

        $payload = $this->mockPayload($payloadData);

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository->shouldReceive('find')->once()->andReturn($line);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository->shouldReceive('getPayload')->once()->andReturn($payload);
        $payloadRepository->shouldNotReceive('updatePayload');

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository->shouldNotReceive('save');

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository
            ->shouldReceive('updateStatus')
            ->once()
            ->with(
                $history,
                Mockery::on(fn(ProductionStatus $status): bool => $status->is(ProductionStatus::RUNNING()))
            );

        $defectiveProductionRepository = Mockery::mock(DefectiveProductionRepository::class);
        $defectiveProductionRepository->shouldNotReceive('save');

        $job = new CountJob(503, 9, $date);
        $job->handle(
            $productionLineRepository,
            $productionHistoryRepository,
            $payloadRepository,
            $productionRepository,
            $defectiveProductionRepository,
        );

        Bus::assertDispatchedSync(FinishChangeoverJob::class);
    }

    public function test_handleはBREAKDOWNかつ指標ライン時にFinishBreakdownJob同期実行を行う(): void
    {
        Bus::fake();
        $this->mockTransactionWithAfterCommit();

        $date = Carbon::parse('2026-04-13 12:30:00');
        $history = $this->makeHistory();
        $line = $this->makeLine(504, false, true, $history);

        $payloadData = $this->makePayloadData();
        $payloadData->addBreakdown($date, true);

        $payload = $this->mockPayload($payloadData);

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository->shouldReceive('find')->once()->andReturn($line);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository->shouldReceive('getPayload')->once()->andReturn($payload);
        $payloadRepository->shouldNotReceive('updatePayload');

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository->shouldNotReceive('save');

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository
            ->shouldReceive('updateStatus')
            ->once()
            ->with(
                $history,
                Mockery::on(fn(ProductionStatus $status): bool => $status->is(ProductionStatus::RUNNING()))
            );

        $defectiveProductionRepository = Mockery::mock(DefectiveProductionRepository::class);
        $defectiveProductionRepository->shouldNotReceive('save');

        $job = new CountJob(504, 11, $date);
        $job->handle(
            $productionLineRepository,
            $productionHistoryRepository,
            $payloadRepository,
            $productionRepository,
            $defectiveProductionRepository,
        );

        Bus::assertDispatchedSync(FinishBreakdownJob::class);
    }

    private function mockPayload(PayloadData $payloadData): Payload
    {
        $payload = Mockery::mock(Payload::class);
        $payload
            ->shouldReceive('getPayloadData')
            ->once()
            ->andReturn($payloadData);

        return $payload;
    }

    private function makePayloadData(): PayloadData
    {
        return new PayloadData(
            lineId: 1,
            defectiveCounts: [502 => 0],
            start: '2026-04-13 11:50:00.000000',
            countSwitch: true,
            cycleTimeMs: 1000,
            overTimeMs: 500,
            plannedOutages: [],
            changeovers: [],
            indicator: true,
        );
    }

    private function makeHistory(): ProductionHistory
    {
        $history = new ProductionHistory();
        $history->production_history_id = 100;
        $history->process_id = 1;
        $history->process_name = '工程A';
        $history->part_number_id = 2;
        $history->part_number_name = 'PN-001';
        $history->goal = 100;
        $history->start = Carbon::parse('2026-04-13 11:00:00');
        $history->cycle_time = 1.0;
        $history->over_time = 0.5;
        $history->status = ProductionStatus::RUNNING();

        return $history;
    }

    private function makeLine(int $lineId, bool $defective, bool $indicator, ProductionHistory $history): ProductionLine
    {
        $line = new ProductionLine();
        $line->production_line_id = $lineId;
        $line->defective = $defective;
        $line->indicator = $indicator;
        $line->setRelation('productionHistory', $history);

        return $line;
    }

    private function makeProduction(int $lineId, Carbon $at): Production
    {
        $production = new Production();
        $production->production_id = 801;
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
