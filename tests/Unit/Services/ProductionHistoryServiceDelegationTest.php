<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\Process;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use App\Repositories\CycleTimeRepository;
use App\Repositories\DefectiveProductionRepository;
use App\Repositories\PartNumberRepository;
use App\Repositories\PayloadRepository;
use App\Repositories\ProcessRepository;
use App\Repositories\ProducerRepository;
use App\Repositories\ProductionHistoryRepository;
use App\Repositories\ProductionLineRepository;
use App\Repositories\ProductionPlannedOutageRepository;
use App\Repositories\ProductionRepository;
use App\Repositories\RaspberryPiRepository;
use App\Repositories\WorkerRepository;
use App\Services\ProductionHistoryService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Pagination\LengthAwarePaginator;
use Mockery;
use Tests\TestCase;

class ProductionHistoryServiceDelegationTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_partNumberOptionsはcycleTimeリポジトリへ委譲する(): void
    {
        $process = new Process();
        $process->process_id = 1;

        $cycleTimeRepository = Mockery::mock(CycleTimeRepository::class);
        $cycleTimeRepository
            ->shouldReceive('notRunningPartNumberOptions')
            ->once()
            ->with($process)
            ->andReturn([3 => 'PN-3']);

        $service = $this->makeService(cycleTime: $cycleTimeRepository);

        $this->assertSame([3 => 'PN-3'], $service->partNumberOptions($process));
    }

    public function test_productedPartNumberOptionsは履歴から重複を吸収して返す(): void
    {
        $process = new Process();
        $process->process_id = 10;

        $h1 = new ProductionHistory(['part_number_name' => 'PN-A']);
        $h2 = new ProductionHistory(['part_number_name' => 'PN-B']);
        $h3 = new ProductionHistory(['part_number_name' => 'PN-A']);

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository
            ->shouldReceive('get')
            ->once()
            ->with(['process_id' => 10], null, ['*'], 'part_number_name')
            ->andReturn(new EloquentCollection([$h1, $h2, $h3]));

        $service = $this->makeService(productionHistory: $productionHistoryRepository);

        $this->assertSame([
            'PN-A' => 'PN-A',
            'PN-B' => 'PN-B',
        ], $service->productedPartNumberOptions($process));
    }

    public function test_historiesは履歴検索をそのまま委譲する(): void
    {
        $paginator = new LengthAwarePaginator([], 0, 50);

        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository
            ->shouldReceive('histories')
            ->once()
            ->with(1, 'PN-1', '2026-01-01', '2026-01-31', 50)
            ->andReturn($paginator);

        $service = $this->makeService(productionHistory: $productionHistoryRepository);

        $this->assertSame(
            $paginator,
            $service->histories(1, 'PN-1', '2026-01-01', '2026-01-31', 50)
        );
    }

    public function test_productionLinesは関連付きで並び順取得を委譲する(): void
    {
        $line = new ProductionLine(['line_name' => 'L1']);

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository
            ->shouldReceive('get')
            ->once()
            ->with(
                ['production_history_id' => 99],
                ['productions', 'defectiveProductions'],
                ['*'],
                'order'
            )
            ->andReturn(new EloquentCollection([$line]));

        $service = $this->makeService(productionLine: $productionLineRepository);

        $actual = $service->productionLines(99);

        $this->assertCount(1, $actual);
    }

    public function test_destroyHistoriesはsoftDeleteを委譲する(): void
    {
        $productionHistoryRepository = Mockery::mock(ProductionHistoryRepository::class);
        $productionHistoryRepository
            ->shouldReceive('softDelete')
            ->once()
            ->with([1, 2, 3])
            ->andReturn(3);

        $service = $this->makeService(productionHistory: $productionHistoryRepository);

        $this->assertSame(3, $service->destroyHistories([1, 2, 3]));
    }

    private function makeService(
        ?CycleTimeRepository $cycleTime = null,
        ?DefectiveProductionRepository $defectiveProduction = null,
        ?PartNumberRepository $partNumber = null,
        ?PayloadRepository $payload = null,
        ?ProcessRepository $process = null,
        ?ProductionHistoryRepository $productionHistory = null,
        ?ProducerRepository $producer = null,
        ?ProductionRepository $production = null,
        ?ProductionLineRepository $productionLine = null,
        ?ProductionPlannedOutageRepository $productionPlannedOutage = null,
        ?RaspberryPiRepository $raspberryPi = null,
        ?WorkerRepository $worker = null,
    ): ProductionHistoryService {
        return new ProductionHistoryService(
            $cycleTime ?? Mockery::mock(CycleTimeRepository::class),
            $defectiveProduction ?? Mockery::mock(DefectiveProductionRepository::class),
            $partNumber ?? Mockery::mock(PartNumberRepository::class),
            $payload ?? Mockery::mock(PayloadRepository::class),
            $process ?? Mockery::mock(ProcessRepository::class),
            $productionHistory ?? Mockery::mock(ProductionHistoryRepository::class),
            $producer ?? Mockery::mock(ProducerRepository::class),
            $production ?? Mockery::mock(ProductionRepository::class),
            $productionLine ?? Mockery::mock(ProductionLineRepository::class),
            $productionPlannedOutage ?? Mockery::mock(ProductionPlannedOutageRepository::class),
            $raspberryPi ?? Mockery::mock(RaspberryPiRepository::class),
            $worker ?? Mockery::mock(WorkerRepository::class),
        );
    }
}
