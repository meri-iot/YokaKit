<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Http\Requests\StoreProcessRequest;
use App\Http\Requests\UpdateProcessRequest;
use App\Models\Process;
use App\Models\ProductionLine;
use App\Repositories\ProcessRepository;
use App\Repositories\ProductionLineRepository;
use App\Services\ProcessService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Mockery;
use Tests\TestCase;

class ProcessServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_allは工程一覧を指定リレーション付きで返す(): void
    {
        $first = new Process(['process_name' => 'P-A']);
        $first->process_id = 1;
        $second = new Process(['process_name' => 'P-B']);
        $second->process_id = 2;

        $processRepository = Mockery::mock(ProcessRepository::class);
        $processRepository
            ->shouldReceive('all')
            ->once()
            ->with('productionHistory.indicatorLine.payload')
            ->andReturn(new EloquentCollection([$first, $second]));

        $service = new ProcessService(
            $processRepository,
            Mockery::mock(ProductionLineRepository::class),
        );

        $actual = $service->all();

        $this->assertSame([1, 2], $actual->pluck('process_id')->all());
    }

    public function test_allProcessInfoは各工程のinfoを収集して返す(): void
    {
        $processA = Mockery::mock(Process::class)->makePartial();
        $processA->shouldReceive('info')->once()->andReturn(['id' => 10, 'name' => 'alpha']);

        $processB = Mockery::mock(Process::class)->makePartial();
        $processB->shouldReceive('info')->once()->andReturn(['id' => 20, 'name' => 'beta']);

        $processRepository = Mockery::mock(ProcessRepository::class);
        $processRepository
            ->shouldReceive('all')
            ->once()
            ->with('partNumbers')
            ->andReturn(new EloquentCollection([$processA, $processB]));

        $service = new ProcessService(
            $processRepository,
            Mockery::mock(ProductionLineRepository::class),
        );

        $actual = $service->allProcessInfo()->all();

        $this->assertSame([
            ['id' => 10, 'name' => 'alpha'],
            ['id' => 20, 'name' => 'beta'],
        ], $actual);
    }

    public function test_storeはリポジトリへ委譲する(): void
    {
        $request = Mockery::mock(StoreProcessRequest::class);

        $processRepository = Mockery::mock(ProcessRepository::class);
        $processRepository
            ->shouldReceive('store')
            ->once()
            ->with($request)
            ->andReturn(true);

        $service = new ProcessService(
            $processRepository,
            Mockery::mock(ProductionLineRepository::class),
        );

        $this->assertTrue($service->store($request));
    }

    public function test_updateはリポジトリへ委譲する(): void
    {
        $request = Mockery::mock(UpdateProcessRequest::class);
        $process = new Process(['process_name' => 'target']);
        $process->process_id = 30;

        $processRepository = Mockery::mock(ProcessRepository::class);
        $processRepository
            ->shouldReceive('update')
            ->once()
            ->with($request, $process)
            ->andReturn(true);

        $service = new ProcessService(
            $processRepository,
            Mockery::mock(ProductionLineRepository::class),
        );

        $this->assertTrue($service->update($request, $process));
    }

    public function test_destroyはリポジトリへ委譲する(): void
    {
        $process = new Process(['process_name' => 'target']);
        $process->process_id = 31;

        $processRepository = Mockery::mock(ProcessRepository::class);
        $processRepository
            ->shouldReceive('destroy')
            ->once()
            ->with($process)
            ->andReturn(true);

        $service = new ProcessService(
            $processRepository,
            Mockery::mock(ProductionLineRepository::class),
        );

        $this->assertTrue($service->destroy($process));
    }

    public function test_productionLinesは停止中ならnullを返す(): void
    {
        /** @var Process&\Mockery\MockInterface $process */
        $process = Mockery::mock(Process::class)->makePartial();
        $process->shouldReceive('isStopped')->once()->andReturn(true);

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository->shouldNotReceive('get');

        $service = new ProcessService(
            Mockery::mock(ProcessRepository::class),
            $productionLineRepository,
        );

        $this->assertNull($service->productionLines($process));
    }

    public function test_productionLinesは稼働中なら生産ライン一覧を返す(): void
    {
        /** @var Process&\Mockery\MockInterface $process */
        $process = Mockery::mock(Process::class)->makePartial();
        $process->production_history_id = 88;
        $process->shouldReceive('isStopped')->once()->andReturn(false);

        $lineA = new ProductionLine(['line_name' => 'L1']);
        $lineB = new ProductionLine(['line_name' => 'L2']);

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository
            ->shouldReceive('get')
            ->once()
            ->with(
                ['production_history_id' => 88],
                ['productions', 'defectiveProductions'],
                ['*'],
                'order'
            )
            ->andReturn(new EloquentCollection([$lineA, $lineB]));

        $service = new ProcessService(
            Mockery::mock(ProcessRepository::class),
            $productionLineRepository,
        );

        $actual = $service->productionLines($process);

        $this->assertNotNull($actual);
        $this->assertCount(2, $actual);
    }
}
