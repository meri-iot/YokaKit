<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\ProductionStatus;
use App\Exceptions\ProductionException;
use App\Jobs\CountJob;
use App\Jobs\FinishBreakdownJob;
use App\Jobs\FinishChangeoverJob;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use App\Repositories\ProductionHistoryRepository;
use App\Repositories\ProductionLineRepository;
use App\Services\ProductionService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ProductionServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_storeは生産ラインが見つからない場合に例外を送出する(): void
    {
        $this->mockTransaction();

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository
            ->shouldReceive('lastProductionLine')
            ->once()
            ->with('192.168.0.10', 1)
            ->andReturn(null);

        $service = new ProductionService(
            Mockery::mock(ProductionHistoryRepository::class),
            $productionLineRepository,
        );

        $this->expectException(ProductionException::class);

        $service->store('192.168.0.10', 5, 1, Carbon::parse('2026-04-09 12:00:00'));
    }

    public function test_storeは停止済み生産履歴の場合に例外を送出する(): void
    {
        $this->mockTransaction();

        $productionLine = $this->makeProductionLine(ProductionStatus::COMPLETE());

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository
            ->shouldReceive('lastProductionLine')
            ->once()
            ->with('192.168.0.20', '7')
            ->andReturn($productionLine);
        $productionLineRepository->shouldNotReceive('updateLineInfo');

        $service = new ProductionService(
            Mockery::mock(ProductionHistoryRepository::class),
            $productionLineRepository,
        );

        $this->expectException(ProductionException::class);

        $service->store('192.168.0.20', 8, '7', Carbon::parse('2026-04-09 12:10:00'));
    }

    public function test_storeはRUNNING時にCountJobをコミット後投入する(): void
    {
        Queue::fake();
        $this->mockTransactionWithAfterCommit();

        $productionLine = $this->makeProductionLine(ProductionStatus::RUNNING());

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository
            ->shouldReceive('lastProductionLine')
            ->once()
            ->with('192.168.0.30', 9)
            ->andReturn($productionLine);
        $productionLineRepository
            ->shouldReceive('updateLineInfo')
            ->once()
            ->with($productionLine, 10)
            ->andReturn(3);

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository->shouldNotReceive('updateStatus');

        $service = new ProductionService($productionHistoryRepository, $productionLineRepository);

        $service->store('192.168.0.30', 10, 9, Carbon::parse('2026-04-09 12:20:00'));

        Queue::assertPushed(CountJob::class, 1);
        Queue::assertNotPushed(FinishChangeoverJob::class);
        Queue::assertNotPushed(FinishBreakdownJob::class);
    }

    public function test_storeはCHANGEOVER時にステータスを更新して終了ジョブを投入する(): void
    {
        Queue::fake();
        $this->mockTransactionWithAfterCommit();

        $productionLine = $this->makeProductionLine(ProductionStatus::CHANGEOVER());

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository
            ->shouldReceive('lastProductionLine')
            ->once()
            ->with('192.168.0.40', 10)
            ->andReturn($productionLine);
        $productionLineRepository
            ->shouldReceive('updateLineInfo')
            ->once()
            ->with($productionLine, 6)
            ->andReturn(1);

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository
            ->shouldReceive('updateStatus')
            ->once()
            ->with(
                $productionLine->productionHistory,
                Mockery::on(fn(ProductionStatus $status) => $status->is(ProductionStatus::RUNNING()))
            );

        $service = new ProductionService($productionHistoryRepository, $productionLineRepository);

        $service->store('192.168.0.40', 6, 10, Carbon::parse('2026-04-09 12:30:00'));

        Queue::assertPushed(FinishChangeoverJob::class, 1);
        Queue::assertNotPushed(CountJob::class);
        Queue::assertNotPushed(FinishBreakdownJob::class);
    }

    public function test_storeはBREAKDOWN時に終了ジョブを投入する(): void
    {
        Queue::fake();
        $this->mockTransactionWithAfterCommit();

        $productionLine = $this->makeProductionLine(ProductionStatus::BREAKDOWN());

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository
            ->shouldReceive('lastProductionLine')
            ->once()
            ->with('192.168.0.50', 11)
            ->andReturn($productionLine);
        $productionLineRepository
            ->shouldReceive('updateLineInfo')
            ->once()
            ->with($productionLine, 12)
            ->andReturn(2);

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository->shouldNotReceive('updateStatus');

        $service = new ProductionService($productionHistoryRepository, $productionLineRepository);

        $service->store('192.168.0.50', 12, 11, Carbon::parse('2026-04-09 12:40:00'));

        Queue::assertPushed(FinishBreakdownJob::class, 1);
        Queue::assertNotPushed(CountJob::class);
        Queue::assertNotPushed(FinishChangeoverJob::class);
    }

    private function makeProductionLine(ProductionStatus $status): ProductionLine
    {
        $history = new ProductionHistory();
        $history->status = $status;

        $productionLine = new ProductionLine();
        $productionLine->production_line_id = 123;
        $productionLine->setRelation('productionHistory', $history);

        return $productionLine;
    }

    private function mockTransaction(): void
    {
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(static function (callable $callback): void {
                $callback();
            });
    }

    private function mockTransactionWithAfterCommit(): void
    {
        $this->mockTransaction();

        DB::shouldReceive('afterCommit')
            ->once()
            ->andReturnUsing(static function (callable $callback): void {
                $callback();
            });
    }
}
