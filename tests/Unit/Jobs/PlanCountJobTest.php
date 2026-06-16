<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Data\PayloadData;
use App\Enums\ProductionStatus;
use App\Events\ProductionSummaryNotification;
use App\Jobs\PlanCountJob;
use App\Models\Payload;
use App\Models\Production;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use App\Repositories\PayloadRepository;
use App\Repositories\ProductionHistoryRepository;
use App\Repositories\ProductionLineRepository;
use App\Repositories\ProductionRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class PlanCountJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();
        Mockery::close();

        parent::tearDown();
    }

    public function test_handleは対象ジョブキー一致時に更新して次回ジョブをafterCommit投入する(): void
    {
        Queue::fake();
        Event::fake([ProductionSummaryNotification::class]);
        $this->mockTransactionWithAfterCommit();

        $planDate = Carbon::parse('2099-04-13 15:00:00');
        $payloadData = $this->makePayloadData(jobKey: 'job-1', indicator: true, operatingTime: 1100);

        $payload = Mockery::mock(Payload::class);
        $payload
            ->shouldReceive('getPayloadData')
            ->once()
            ->andReturn($payloadData);

        $history = $this->makeHistory();

        $line = new ProductionLine();
        $line->production_line_id = 901;
        $line->indicator = true;
        $line->setRelation('productionHistory', $history);
        $line->setRelation('payload', $payload);

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository
            ->shouldReceive('find')
            ->once()
            ->with(901, ['productionHistory', 'payload'])
            ->andReturn($line);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository
            ->shouldReceive('updatePayload')
            ->once()
            ->with($payload, Mockery::type('callable'))
            ->andReturnUsing(function (Payload $savedPayload, callable $callback) use ($payloadData): PayloadData {
                $callback($payloadData);
                return $payloadData;
            });

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository
            ->shouldReceive('save')
            ->once()
            ->with(901, Mockery::type(PayloadData::class))
            ->andReturn($this->makeProduction(901));

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository->shouldReceive('makeProductionSummary')
            ->once()
            ->andReturn($this->makeProductionSummaryData());

        $job = new PlanCountJob(901, 500, $planDate, false, 'job-1');
        $job->handle($productionLineRepository, $payloadRepository, $productionRepository, $productionHistoryRepository);

        Event::assertDispatched(ProductionSummaryNotification::class);
        Queue::assertPushed(PlanCountJob::class, function (PlanCountJob $job) use ($planDate): bool {
            return $job->delay instanceof Carbon
                && $job->delay->gt($planDate)
                && $job->afterCommit === true;
        });
    }

    public function test_handleはisChangeover時に次回遅延がcycleTimeMs固定になる(): void
    {
        Queue::fake();
        Event::fake([ProductionSummaryNotification::class]);
        $this->mockTransactionWithAfterCommit();

        $planDate = Carbon::parse('2099-04-13 15:10:00');
        $payloadData = $this->makePayloadData(jobKey: 'job-2', indicator: true, operatingTime: 1300);

        $payload = Mockery::mock(Payload::class);
        $payload
            ->shouldReceive('getPayloadData')
            ->once()
            ->andReturn($payloadData);

        $history = $this->makeHistory();

        $line = new ProductionLine();
        $line->production_line_id = 902;
        $line->indicator = true;
        $line->setRelation('productionHistory', $history);
        $line->setRelation('payload', $payload);

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository->shouldReceive('find')->once()->andReturn($line);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository
            ->shouldReceive('updatePayload')
            ->once()
            ->andReturnUsing(function (Payload $savedPayload, callable $callback) use ($payloadData): PayloadData {
                $callback($payloadData);
                return $payloadData;
            });

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository
            ->shouldReceive('save')
            ->once()
            ->andReturn($this->makeProduction(902));

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository->shouldReceive('makeProductionSummary')
            ->once()
            ->andReturn($this->makeProductionSummaryData());

        $job = new PlanCountJob(902, 500, $planDate, true, 'job-2');
        $job->handle($productionLineRepository, $payloadRepository, $productionRepository, $productionHistoryRepository);

        Queue::assertPushed(PlanCountJob::class, function (PlanCountJob $job): bool {
            return $job->delay instanceof Carbon
                && $job->delay->equalTo(Carbon::parse('2099-04-13 15:10:00.500000'))
                && $job->afterCommit === true;
        });
    }

    public function test_handleはcycleTimeMs不正時に再投入を行わない(): void
    {
        Queue::fake();
        Event::fake([ProductionSummaryNotification::class]);
        $this->mockTransactionWithAfterCommit();

        $payloadData = $this->makePayloadData(jobKey: 'job-guard', indicator: true, operatingTime: 900);

        $payload = Mockery::mock(Payload::class);
        $payload
            ->shouldReceive('getPayloadData')
            ->once()
            ->andReturn($payloadData);

        $history = $this->makeHistory();

        $line = new ProductionLine();
        $line->production_line_id = 904;
        $line->indicator = true;
        $line->setRelation('productionHistory', $history);
        $line->setRelation('payload', $payload);

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository->shouldReceive('find')->once()->andReturn($line);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository->shouldNotReceive('updatePayload');

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository->shouldNotReceive('save');

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);

        $job = new PlanCountJob(904, 0, Carbon::parse('2099-04-13 15:40:00'), false, 'job-guard');
        $job->handle($productionLineRepository, $payloadRepository, $productionRepository, $productionHistoryRepository);

        Event::assertNotDispatched(ProductionSummaryNotification::class);
        Queue::assertNotPushed(PlanCountJob::class);
    }

    public function test_handleは遅延実行時でも次回時刻のミリ秒位相を維持する(): void
    {
        Queue::fake();
        Event::fake([ProductionSummaryNotification::class]);
        $this->mockTransactionWithAfterCommit();

        Carbon::setTestNow(Carbon::parse('2099-04-13 15:10:00.120000'));

        $planDate = Carbon::parse('2099-04-13 15:10:00.000000');
        $payloadData = $this->makePayloadData(jobKey: 'job-cadence', indicator: true, operatingTime: 1000);

        $payload = Mockery::mock(Payload::class);
        $payload
            ->shouldReceive('getPayloadData')
            ->once()
            ->andReturn($payloadData);

        $history = $this->makeHistory();

        $line = new ProductionLine();
        $line->production_line_id = 905;
        $line->indicator = true;
        $line->setRelation('productionHistory', $history);
        $line->setRelation('payload', $payload);

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository->shouldReceive('find')->once()->andReturn($line);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository
            ->shouldReceive('updatePayload')
            ->once()
            ->andReturnUsing(function (Payload $savedPayload, callable $callback) use ($payloadData): PayloadData {
                $callback($payloadData);
                return $payloadData;
            });

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository
            ->shouldReceive('save')
            ->once()
            ->andReturn($this->makeProduction(905));

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository->shouldReceive('makeProductionSummary')
            ->once()
            ->andReturn($this->makeProductionSummaryData());

        $job = new PlanCountJob(905, 5000, $planDate, false, 'job-cadence');
        $job->handle($productionLineRepository, $payloadRepository, $productionRepository, $productionHistoryRepository);

        Queue::assertPushed(PlanCountJob::class, function (PlanCountJob $job): bool {
            return $job->delay instanceof Carbon
                && $job->delay->equalTo(Carbon::parse('2099-04-13 15:10:05.000000'))
                && $job->afterCommit === true;
        });
    }

    public function test_handleはジョブキー不一致なら更新も再投入もしない(): void
    {
        Queue::fake();
        Event::fake([ProductionSummaryNotification::class]);
        $this->mockTransactionWithAfterCommit();

        $payloadData = $this->makePayloadData(jobKey: 'actual-key', indicator: true, operatingTime: 900);

        $payload = Mockery::mock(Payload::class);
        $payload
            ->shouldReceive('getPayloadData')
            ->once()
            ->andReturn($payloadData);

        $history = $this->makeHistory();

        $line = new ProductionLine();
        $line->production_line_id = 903;
        $line->indicator = true;
        $line->setRelation('productionHistory', $history);
        $line->setRelation('payload', $payload);

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository->shouldReceive('find')->once()->andReturn($line);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository->shouldNotReceive('updatePayload');

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository->shouldNotReceive('save');

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);

        $job = new PlanCountJob(903, 500, Carbon::parse('2026-04-13 15:20:00'), false, 'different-key');
        $job->handle($productionLineRepository, $payloadRepository, $productionRepository, $productionHistoryRepository);

        Event::assertNotDispatched(ProductionSummaryNotification::class);
        Queue::assertNotPushed(PlanCountJob::class);
    }

    public function test_handleは生産ライン未取得時に例外を送出する(): void
    {
        $this->mockTransactionWithAfterCommit();

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository
            ->shouldReceive('find')
            ->once()
            ->with(999, ['productionHistory', 'payload'])
            ->andReturn(null);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository->shouldNotReceive('updatePayload');

        $productionRepository = Mockery::mock(ProductionRepository::class);
        $productionRepository->shouldNotReceive('save');

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);

        $job = new PlanCountJob(999, 500, Carbon::parse('2026-04-13 15:30:00'), false, 'job-x');

        $this->expectException(ModelNotFoundException::class);

        $job->handle($productionLineRepository, $payloadRepository, $productionRepository, $productionHistoryRepository);
    }

    private function makePayloadData(string $jobKey, bool $indicator, int $operatingTime): PayloadData
    {
        $payloadData = new PayloadData(
            lineId: 1,
            defectiveCounts: [],
            start: '2026-04-13 14:50:00.000000',
            countSwitch: true,
            cycleTimeMs: 500,
            overTimeMs: 500,
            plannedOutages: [],
            changeovers: [],
            indicator: $indicator,
        );

        $payloadData->jobKey = $jobKey;
        $payloadData->operatingTime = $operatingTime;

        return $payloadData;
    }

    private function makeHistory(): ProductionHistory
    {
        $history = new ProductionHistory();
        $history->production_history_id = 400;
        $history->process_id = 1;
        $history->process_name = '工程A';
        $history->part_number_id = 2;
        $history->part_number_name = 'PN-001';
        $history->goal = 100;
        $history->start = Carbon::parse('2026-04-13 14:00:00');
        $history->status = ProductionStatus::RUNNING();

        return $history;
    }

    private function makeProduction(int $lineId): Production
    {
        $production = new Production();
        $production->production_id = 990;
        $production->production_line_id = $lineId;
        $production->count = 1;
        $production->status = ProductionStatus::RUNNING();
        $production->at = Carbon::parse('2026-04-13 15:00:00');

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
