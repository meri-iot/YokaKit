<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Http\Requests\UpdateLineWorkerRequest;
use App\Models\Line;
use App\Models\Process;
use App\Models\Producer;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use App\Models\Worker;
use App\Repositories\LineRepository;
use App\Repositories\ProcessRepository;
use App\Repositories\ProducerRepository;
use App\Repositories\ProductionLineRepository;
use App\Repositories\WorkerRepository;
use App\Services\SwitchService;
use App\Services\Utility;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Mockery;
use Tests\TestCase;

class SwitchServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_workerOptionsはリポジトリへ委譲する(): void
    {
        $workerRepository = Mockery::mock(WorkerRepository::class);
        $workerRepository
            ->shouldReceive('options')
            ->once()
            ->andReturn([1 => 'Worker A', 2 => 'Worker B']);

        $service = new SwitchService(
            Mockery::mock(LineRepository::class),
            Mockery::mock(ProcessRepository::class),
            Mockery::mock(ProducerRepository::class),
            Mockery::mock(ProductionLineRepository::class),
            $workerRepository,
        );

        $this->assertSame([1 => 'Worker A', 2 => 'Worker B'], $service->workerOptions());
    }

    public function test_workersはリポジトリへ委譲する(): void
    {
        $worker1 = new Worker();
        $worker1->worker_id = 1;
        $worker2 = new Worker();
        $worker2->worker_id = 2;

        $workerRepository = Mockery::mock(WorkerRepository::class);
        $workerRepository
            ->shouldReceive('all')
            ->once()
            ->andReturn(new EloquentCollection([$worker1, $worker2]));

        $service = new SwitchService(
            Mockery::mock(LineRepository::class),
            Mockery::mock(ProcessRepository::class),
            Mockery::mock(ProducerRepository::class),
            Mockery::mock(ProductionLineRepository::class),
            $workerRepository,
        );

        $actual = $service->workers();

        $this->assertCount(2, $actual);
    }

    public function test_processesはリレーション指定付きで委譲する(): void
    {
        $process = new Process();
        $process->process_id = 1;

        $processRepository = Mockery::mock(ProcessRepository::class);
        $processRepository
            ->shouldReceive('all')
            ->once()
            ->with(['partNumbers', 'lines', 'productionHistory.indicatorLine.payload'])
            ->andReturn(new EloquentCollection([$process]));

        $service = new SwitchService(
            Mockery::mock(LineRepository::class),
            $processRepository,
            Mockery::mock(ProducerRepository::class),
            Mockery::mock(ProductionLineRepository::class),
            Mockery::mock(WorkerRepository::class),
        );

        $actual = $service->processes();

        $this->assertCount(1, $actual);
    }

    public function test_updateLineWorkerは稼働中でなければラインの作業者のみ更新する(): void
    {
        $this->mockTransaction();

        $request = Mockery::mock(UpdateLineWorkerRequest::class);
        $request->lines = [
            ['line_id' => 10, 'worker_id' => 20],
        ];

        $process = new Process();
        $process->process_id = 1;
        $process->setRelation('productionHistory', null);

        $lineRepository = Mockery::mock(LineRepository::class);
        $lineRepository
            ->shouldReceive('updateWorker')
            ->once()
            ->with(10, 20)
            ->andReturn(true);

        $service = new SwitchService(
            $lineRepository,
            Mockery::mock(ProcessRepository::class),
            Mockery::mock(ProducerRepository::class),
            Mockery::mock(ProductionLineRepository::class),
            Mockery::mock(WorkerRepository::class),
        );

        $service->updateLineWorker($request, $process);

        // 期待する動作：ラインの作業者のみが更新されて、生産者情報は触れられない
        $this->assertTrue(true);
    }

    public function test_updateLineWorkerは該当生産ラインが見つからない場合はラインのみ更新する(): void
    {
        $this->mockTransaction();

        $request = Mockery::mock(UpdateLineWorkerRequest::class);
        $request->lines = [
            ['line_id' => 11, 'worker_id' => 21],
        ];

        $history = new ProductionHistory();
        $history->production_history_id = 100;
        $history->setRelation('productionLines', new EloquentCollection([]));

        $process = new Process();
        $process->process_id = 2;
        $process->setRelation('productionHistory', $history);

        $lineRepository = Mockery::mock(LineRepository::class);
        $lineRepository
            ->shouldReceive('updateWorker')
            ->once()
            ->with(11, 21)
            ->andReturn(true);

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository
            ->shouldReceive('first')
            ->once()
            ->with([
                'line_id' => 11,
                'production_history_id' => 100,
            ])
            ->andReturn(null);

        $service = new SwitchService(
            $lineRepository,
            Mockery::mock(ProcessRepository::class),
            Mockery::mock(ProducerRepository::class),
            $productionLineRepository,
            Mockery::mock(WorkerRepository::class),
        );

        $service->updateLineWorker($request, $process);
        
        $this->assertTrue(true);
    }

    public function test_updateLineWorkerは不良品ラインの場合は生産者を変更しない(): void
    {
        $this->mockTransaction();

        $request = Mockery::mock(UpdateLineWorkerRequest::class);
        $request->lines = [
            ['line_id' => 12, 'worker_id' => 22],
        ];

        $productionLine = new ProductionLine();
        $productionLine->production_line_id = 200;
        $productionLine->defective = true;

        $history = new ProductionHistory();
        $history->production_history_id = 101;
        $history->setRelation('productionLines', new EloquentCollection([$productionLine]));

        $process = new Process();
        $process->process_id = 3;
        $process->setRelation('productionHistory', $history);

        $lineRepository = Mockery::mock(LineRepository::class);
        $lineRepository
            ->shouldReceive('updateWorker')
            ->once()
            ->with(12, 22)
            ->andReturn(true);

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository
            ->shouldReceive('first')
            ->once()
            ->andReturn($productionLine);

        $producerRepository = Mockery::mock(ProducerRepository::class);
        $producerRepository->shouldNotReceive('findBy');

        $service = new SwitchService(
            $lineRepository,
            Mockery::mock(ProcessRepository::class),
            $producerRepository,
            $productionLineRepository,
            Mockery::mock(WorkerRepository::class),
        );

        $service->updateLineWorker($request, $process);
        
        $this->assertTrue(true);
    }

    public function test_updateLineWorkerは既存生産者を新規作業者に置き換える(): void
    {
        $this->mockTransaction();

        $request = Mockery::mock(UpdateLineWorkerRequest::class);
        $request->lines = [
            ['line_id' => 13, 'worker_id' => 23],
        ];

        $productionLine = new ProductionLine();
        $productionLine->production_line_id = 201;
        $productionLine->defective = false;

        $history = new ProductionHistory();
        $history->production_history_id = 102;
        $history->setRelation('productionLines', new EloquentCollection([$productionLine]));

        $process = new Process();
        $process->process_id = 4;
        $process->setRelation('productionHistory', $history);

        $existingProducer = new Producer();
        $existingProducer->worker_id = 24;

        $newWorker = new Worker();
        $newWorker->worker_id = 23;

        $lineRepository = Mockery::mock(LineRepository::class);
        $lineRepository
            ->shouldReceive('updateWorker')
            ->once()
            ->with(13, 23)
            ->andReturn(true);

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository
            ->shouldReceive('first')
            ->once()
            ->andReturn($productionLine);

        $producerRepository = Mockery::mock(ProducerRepository::class);
        $producerRepository
            ->shouldReceive('findBy')
            ->once()
            ->with(201)
            ->andReturn($existingProducer);
        $producerRepository
            ->shouldReceive('stop')
            ->once()
            ->with($existingProducer, Mockery::type('Illuminate\\Support\\Carbon'))
            ->andReturnNull();
        $producerRepository
            ->shouldReceive('save')
            ->once()
            ->with($newWorker, 201, Mockery::type('Illuminate\\Support\\Carbon'))
            ->andReturn(true);

        $workerRepository = Mockery::mock(WorkerRepository::class);
        $workerRepository
            ->shouldReceive('find')
            ->once()
            ->with(23)
            ->andReturn($newWorker);

        $service = new SwitchService(
            $lineRepository,
            Mockery::mock(ProcessRepository::class),
            $producerRepository,
            $productionLineRepository,
            $workerRepository,
        );

        $service->updateLineWorker($request, $process);
        $this->assertTrue(true); // Mockery expectations verified
    }

    public function test_updateLineWorkerは生産者がない場合は新規登録する(): void
    {
        $this->mockTransaction();

        $request = Mockery::mock(UpdateLineWorkerRequest::class);
        $request->lines = [
            ['line_id' => 14, 'worker_id' => 25],
        ];

        $productionLine = new ProductionLine();
        $productionLine->production_line_id = 202;
        $productionLine->defective = false;

        $history = new ProductionHistory();
        $history->production_history_id = 103;
        $history->setRelation('productionLines', new EloquentCollection([$productionLine]));

        $process = new Process();
        $process->process_id = 5;
        $process->setRelation('productionHistory', $history);

        $newWorker = new Worker();
        $newWorker->worker_id = 25;

        $lineRepository = Mockery::mock(LineRepository::class);
        $lineRepository
            ->shouldReceive('updateWorker')
            ->once()
            ->with(14, 25)
            ->andReturn(true);

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository
            ->shouldReceive('first')
            ->once()
            ->andReturn($productionLine);

        $producerRepository = Mockery::mock(ProducerRepository::class);
        $producerRepository
            ->shouldReceive('findBy')
            ->once()
            ->with(202)
            ->andReturn(null);
        $producerRepository
            ->shouldReceive('save')
            ->once()
            ->with($newWorker, 202, Mockery::type('Illuminate\\Support\\Carbon'))
            ->andReturn(true);

        $workerRepository = Mockery::mock(WorkerRepository::class);
        $workerRepository
            ->shouldReceive('find')
            ->once()
            ->with(25)
            ->andReturn($newWorker);

        $service = new SwitchService(
            $lineRepository,
            Mockery::mock(ProcessRepository::class),
            $producerRepository,
            $productionLineRepository,
            $workerRepository,
        );

        $service->updateLineWorker($request, $process);
        $this->assertTrue(true); // Mockery expectations verified
    }

    public function test_updateLineWorkerは既存生産者を削除する(): void
    {
        $this->mockTransaction();

        $request = Mockery::mock(UpdateLineWorkerRequest::class);
        $request->lines = [
            ['line_id' => 15, 'worker_id' => null],
        ];

        $productionLine = new ProductionLine();
        $productionLine->production_line_id = 203;
        $productionLine->defective = false;

        $history = new ProductionHistory();
        $history->production_history_id = 104;
        $history->setRelation('productionLines', new EloquentCollection([$productionLine]));

        $process = new Process();
        $process->process_id = 6;
        $process->setRelation('productionHistory', $history);

        $existingProducer = new Producer();
        $existingProducer->worker_id = 26;

        $lineRepository = Mockery::mock(LineRepository::class);
        $lineRepository
            ->shouldReceive('updateWorker')
            ->once()
            ->with(15, null)
            ->andReturn(true);

        $productionLineRepository = Mockery::mock(ProductionLineRepository::class);
        $productionLineRepository
            ->shouldReceive('first')
            ->once()
            ->andReturn($productionLine);

        $producerRepository = Mockery::mock(ProducerRepository::class);
        $producerRepository
            ->shouldReceive('findBy')
            ->once()
            ->with(203)
            ->andReturn($existingProducer);
        $producerRepository
            ->shouldReceive('stop')
            ->once()
            ->with($existingProducer, Mockery::type('Illuminate\\Support\\Carbon'))
            ->andReturnNull();

        $service = new SwitchService(
            $lineRepository,
            Mockery::mock(ProcessRepository::class),
            $producerRepository,
            $productionLineRepository,
            Mockery::mock(WorkerRepository::class),
        );

        $service->updateLineWorker($request, $process);
        $this->assertTrue(true); // Mockery expectations verified
    }

    private function mockTransaction(): void
    {
        DB::shouldReceive('transaction')
            ->once()
            ->andReturnUsing(static function (callable $callback): void {
                $callback();
            });
    }
}
