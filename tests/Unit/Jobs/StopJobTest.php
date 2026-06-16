<?php

declare(strict_types=1);

namespace Tests\Unit\Jobs;

use App\Data\PayloadData;
use App\Enums\ProductionStatus;
use App\Events\ProductionSummaryNotification;
use App\Jobs\StopJob;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use App\Repositories\PayloadRepository;
use App\Repositories\ProductionHistoryRepository;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Mockery;
use Tests\TestCase;

class StopJobTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_handleはindicatorラインのイベントをafterCommitでdispatchする(): void
    {
        Event::fake([ProductionSummaryNotification::class]);
        $this->mockTransactionWithAfterCommit();

        $date = Carbon::parse('2026-04-13 15:00:00');
        $line = $this->makeLine(501, true);
        $history = $this->makeHistory([$line]);
        $payloadData = $this->makePayloadData();

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository
            ->shouldReceive('find')
            ->once()
            ->with(100, ['productionLines'])
            ->andReturn($history);

        $productionHistoryRepository->shouldReceive('makeProductionSummary')
            ->once()
            ->andReturn($this->makeProductionSummaryData());

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository
            ->shouldReceive('updatePayload')
            ->once()
            ->with($line, Mockery::type('callable'))
            ->andReturnUsing(function (ProductionLine $target, callable $callback) use ($payloadData): PayloadData {
                $callback($payloadData);
                return $payloadData;
            });

        $job = new StopJob(100, $date, true);
        $job->handle($productionHistoryRepository, $payloadRepository);

        Event::assertDispatched(ProductionSummaryNotification::class);
    }

    public function test_handleはisDispatchEventがfalseの場合にイベントをdispatchしない(): void
    {
        Event::fake([ProductionSummaryNotification::class]);
        $this->mockTransactionWithAfterCommit();

        $date = Carbon::parse('2026-04-13 15:00:00');
        $line = $this->makeLine(502, true);
        $history = $this->makeHistory([$line]);
        $payloadData = $this->makePayloadData();

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($history);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository
            ->shouldReceive('updatePayload')
            ->once()
            ->andReturn($payloadData);

        $job = new StopJob(100, $date, false);
        $job->handle($productionHistoryRepository, $payloadRepository);

        Event::assertNotDispatched(ProductionSummaryNotification::class);
    }

    public function test_handleはindicatorがfalseの場合にイベントをdispatchしない(): void
    {
        Event::fake([ProductionSummaryNotification::class]);
        $this->mockTransactionWithAfterCommit();

        $date = Carbon::parse('2026-04-13 15:00:00');
        $line = $this->makeLine(503, false);
        $history = $this->makeHistory([$line]);
        $payloadData = $this->makePayloadData();

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository
            ->shouldReceive('find')
            ->once()
            ->andReturn($history);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository
            ->shouldReceive('updatePayload')
            ->once()
            ->andReturn($payloadData);

        $job = new StopJob(100, $date, true);
        $job->handle($productionHistoryRepository, $payloadRepository);

        Event::assertNotDispatched(ProductionSummaryNotification::class);
    }

    public function test_handleは生産履歴が未存在の場合に例外を送出する(): void
    {
        $this->mockTransactionWithAfterCommit();

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository
            ->shouldReceive('find')
            ->once()
            ->with(999, ['productionLines'])
            ->andReturn(null);

        $payloadRepository = Mockery::mock(PayloadRepository::class);
        $payloadRepository->shouldNotReceive('updatePayload');

        $this->expectException(ModelNotFoundException::class);

        $job = new StopJob(999, Carbon::parse('2026-04-13 15:00:00'), true);
        $job->handle($productionHistoryRepository, $payloadRepository);
    }

    // ---- ヘルパーメソッド ----

    private function makeHistory(array $lines): ProductionHistory
    {
        $history = new ProductionHistory();
        $history->production_history_id = 100;
        $history->process_name = '工程A';
        $history->part_number_name = 'PN-001';
        $history->status = ProductionStatus::RUNNING();
        $history->start = Carbon::parse('2026-04-13 14:00:00');
        $history->setRelation('productionLines', new EloquentCollection($lines));

        return $history;
    }

    private function makeLine(int $lineId, bool $indicator): ProductionLine
    {
        $line = new ProductionLine();
        $line->production_line_id = $lineId;
        $line->indicator = $indicator;

        return $line;
    }

    private function makePayloadData(): PayloadData
    {
        return new PayloadData(
            lineId: 1,
            defectiveCounts: [],
            start: '2026-04-13 14:50:00.000000',
            countSwitch: true,
            cycleTimeMs: 500,
            overTimeMs: 500,
            plannedOutages: [],
            changeovers: [],
            indicator: true,
        );
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
