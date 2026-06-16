<?php

namespace Tests\Unit;

use App\Http\Requests\StoreProductionHistoryRequest;
use App\Models\CycleTime;
use App\Models\PartNumber;
use App\Models\Process;
use App\Models\ProductionHistory;
use App\Models\RaspberryPi;
use App\Enums\ProductionStatus;
use App\Http\Requests\StopProductionRequest;
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
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Mockery;
use Tests\TestCase;

class ProductionHistoryServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_stopFromApiは工程が消失している場合にModelNotFoundExceptionを投げる(): void
    {
        $processRepository = Mockery::mock(ProcessRepository::class);
        $processRepository
            ->shouldReceive('first')
            ->once()
            ->with(['process_name' => 'missing-process'])
            ->andReturn(null);

        $request = Mockery::mock(StopProductionRequest::class);
        $request
            ->shouldReceive('json')
            ->once()
            ->with('processName')
            ->andReturn('missing-process');

        $service = $this->makeService(process: $processRepository);

        $this->expectException(ModelNotFoundException::class);

        $service->stopFromApi($request);
    }

    public function test_switchPartNumberFromFormはライン未設定の工程でfalseを返す(): void
    {
        $process = new Process();
        $process->process_id = 1;
        $process->setRelation('raspberryPis', new EloquentCollection());

        $request = StoreProductionHistoryRequest::create('/production-history', 'POST', [
            'part_number_id' => 10,
            'status' => ProductionStatus::RUNNING(),
            'goal' => 100,
        ]);

        $service = $this->makeService();

        $this->assertFalse($service->switchPartNumberFromForm($request, $process));
    }

    public function test_switchPartNumberFromFormはサイクルタイム未設定でfalseを返す(): void
    {
        $process = new Process();
        $process->process_id = 2;
        $process->setRelation('raspberryPis', new EloquentCollection([new RaspberryPi()]));

        $request = StoreProductionHistoryRequest::create('/production-history', 'POST', [
            'part_number_id' => 99,
            'status' => ProductionStatus::RUNNING(),
            'goal' => 100,
        ]);

        $cycleTimeRepository = Mockery::mock(CycleTimeRepository::class);
        $cycleTimeRepository
            ->shouldReceive('first')
            ->once()
            ->with([
                'process_id' => 2,
                'part_number_id' => 99,
            ])
            ->andReturn(null);

        $service = $this->makeService(cycleTime: $cycleTimeRepository);

        $this->assertFalse($service->switchPartNumberFromForm($request, $process));
    }

    public function test_switchPartNumberFromApiは工程または品番未存在でfalseを返す(): void
    {
        $request = Mockery::mock(\App\Http\Requests\SwitchPartNumberRequestFromApi::class);
        $request->shouldReceive('json')->with('processName')->andReturn('missing-process');
        $request->shouldReceive('json')->with('partNumberName')->andReturn('missing-part');
        $request->shouldReceive('json')->with('goal')->andReturn(null);
        $request->shouldReceive('json')->with('force')->andReturn(false);
        $request->shouldReceive('json')->with('changeover')->andReturn(false);
        $request->shouldReceive('all')->once()->andReturn([]);

        $processRepository = Mockery::mock(ProcessRepository::class);
        $processRepository
            ->shouldReceive('first')
            ->once()
            ->with(['process_name' => 'missing-process'])
            ->andReturn(null);

        $partNumberRepository = Mockery::mock(PartNumberRepository::class);
        $partNumberRepository
            ->shouldReceive('first')
            ->once()
            ->with(['part_number_name' => 'missing-part'])
            ->andReturn(null);

        $service = $this->makeService(
            process: $processRepository,
            partNumber: $partNumberRepository,
        );

        $this->assertFalse($service->switchPartNumberFromApi($request));
    }

    public function test_switchPartNumberFromMqttは工程名特定不可でfalseを返す(): void
    {
        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository
            ->shouldReceive('first')
            ->once()
            ->with(['ip_address' => '192.168.0.10'], 'processes')
            ->andReturn(null);

        $service = $this->makeService(raspberryPi: $raspberryPiRepository);

        $this->assertFalse($service->switchPartNumberFromMqtt('192.168.0.10', 'AA:BB', '123456'));
    }

    public function test_switchPartNumberFromApiはサイクルタイム未存在でfalseを返す(): void
    {
        $request = Mockery::mock(\App\Http\Requests\SwitchPartNumberRequestFromApi::class);
        $request->shouldReceive('json')->with('processName')->andReturn('process-a');
        $request->shouldReceive('json')->with('partNumberName')->andReturn('part-a');
        $request->shouldReceive('json')->with('goal')->andReturn(100);
        $request->shouldReceive('json')->with('force')->andReturn(false);
        $request->shouldReceive('json')->with('changeover')->andReturn(false);
        $request->shouldReceive('all')->once()->andReturn([]);

        $process = new Process();
        $process->process_id = 10;
        $part = new PartNumber();
        $part->part_number_id = 20;

        $processRepository = Mockery::mock(ProcessRepository::class);
        $processRepository->shouldReceive('first')->once()->with(['process_name' => 'process-a'])->andReturn($process);

        $partNumberRepository = Mockery::mock(PartNumberRepository::class);
        $partNumberRepository->shouldReceive('first')->once()->with(['part_number_name' => 'part-a'])->andReturn($part);

        $cycleTimeRepository = Mockery::mock(CycleTimeRepository::class);
        $cycleTimeRepository
            ->shouldReceive('first')
            ->once()
            ->with(['process_id' => 10, 'part_number_id' => 20])
            ->andReturn(null);

        $service = $this->makeService(
            cycleTime: $cycleTimeRepository,
            partNumber: $partNumberRepository,
            process: $processRepository,
        );

        $this->assertFalse($service->switchPartNumberFromApi($request));
    }

    public function test_switchPartNumberFromApiは稼働中かつforceなしでfalseを返す(): void
    {
        $request = Mockery::mock(\App\Http\Requests\SwitchPartNumberRequestFromApi::class);
        $request->shouldReceive('json')->with('processName')->andReturn('process-b');
        $request->shouldReceive('json')->with('partNumberName')->andReturn('part-b');
        $request->shouldReceive('json')->with('goal')->andReturn(100);
        $request->shouldReceive('json')->with('force')->andReturn(false);
        $request->shouldReceive('json')->with('changeover')->andReturn(true);

        /** @var Process&\Mockery\MockInterface $process */
        $process = Mockery::mock(Process::class)->makePartial();
        $process->process_id = 11;
        $process->shouldReceive('isStopped')->once()->andReturn(false);

        $part = new PartNumber();
        $part->part_number_id = 21;

        $cycleTime = new CycleTime();

        $processRepository = Mockery::mock(ProcessRepository::class);
        $processRepository->shouldReceive('first')->once()->with(['process_name' => 'process-b'])->andReturn($process);

        $partNumberRepository = Mockery::mock(PartNumberRepository::class);
        $partNumberRepository->shouldReceive('first')->once()->with(['part_number_name' => 'part-b'])->andReturn($part);

        $cycleTimeRepository = Mockery::mock(CycleTimeRepository::class);
        $cycleTimeRepository
            ->shouldReceive('first')
            ->once()
            ->with(['process_id' => 11, 'part_number_id' => 21])
            ->andReturn($cycleTime);

        $service = $this->makeService(
            cycleTime: $cycleTimeRepository,
            partNumber: $partNumberRepository,
            process: $processRepository,
        );

        $this->assertFalse($service->switchPartNumberFromApi($request));
    }

    public function test_switchPartNumberFromMqttは対象品番が見つからなければfalseを返す(): void
    {
        $resolvedProcess = new Process(['process_name' => 'proc-1']);
        $resolvedProcess->process_id = 30;

        $raspi = new RaspberryPi();
        $raspi->setRelation('processes', new EloquentCollection([$resolvedProcess]));

        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository
            ->shouldReceive('first')
            ->once()
            ->with(['ip_address' => '192.168.1.10'], 'processes')
            ->andReturn($raspi);

        $processRepository = Mockery::mock(ProcessRepository::class);
        $processRepository
            ->shouldReceive('first')
            ->once()
            ->with(['process_name' => 'proc-1'])
            ->andReturn($resolvedProcess);

        $partNumberRepository = Mockery::mock(PartNumberRepository::class);
        $partNumberRepository
            ->shouldReceive('first')
            ->once()
            ->with(['barcode' => 'barcode-404'])
            ->andReturn(null);

        $service = $this->makeService(
            raspberryPi: $raspberryPiRepository,
            process: $processRepository,
            partNumber: $partNumberRepository,
        );

        $this->assertFalse($service->switchPartNumberFromMqtt('192.168.1.10', 'AA:BB:CC', 'barcode-404'));
    }

    public function test_switchPartNumberFromMqttは同一品番を生産中ならfalseを返す(): void
    {
        $history = new ProductionHistory(['part_number_name' => 'PN-1']);

        $resolvedProcess = new Process(['process_name' => 'proc-2']);
        $resolvedProcess->process_id = 31;
        $resolvedProcess->setRelation('productionHistory', $history);

        $raspi = new RaspberryPi();
        $raspi->setRelation('processes', new EloquentCollection([$resolvedProcess]));

        $part = new PartNumber(['part_number_name' => 'PN-1']);
        $part->part_number_id = 40;

        $cycleTime = new CycleTime();

        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository
            ->shouldReceive('first')
            ->once()
            ->with(['ip_address' => '192.168.1.11'], 'processes')
            ->andReturn($raspi);

        $processRepository = Mockery::mock(ProcessRepository::class);
        $processRepository
            ->shouldReceive('first')
            ->once()
            ->with(['process_name' => 'proc-2'])
            ->andReturn($resolvedProcess);

        $partNumberRepository = Mockery::mock(PartNumberRepository::class);
        $partNumberRepository
            ->shouldReceive('first')
            ->once()
            ->with(['barcode' => 'barcode-1'])
            ->andReturn($part);

        $cycleTimeRepository = Mockery::mock(CycleTimeRepository::class);
        $cycleTimeRepository
            ->shouldReceive('first')
            ->once()
            ->with(['process_id' => 31, 'part_number_id' => 40])
            ->andReturn($cycleTime);

        $service = $this->makeService(
            cycleTime: $cycleTimeRepository,
            raspberryPi: $raspberryPiRepository,
            process: $processRepository,
            partNumber: $partNumberRepository,
        );

        $this->assertFalse($service->switchPartNumberFromMqtt('192.168.1.11', 'AA:BB:DD', 'barcode-1'));
    }

    public function test_switchPartNumberFromMqttは工程候補複数かつ作業者不明ならfalseを返す(): void
    {
        $procA = new Process(['process_name' => 'proc-a']);
        $procB = new Process(['process_name' => 'proc-b']);

        $raspi = new RaspberryPi();
        $raspi->setRelation('processes', new EloquentCollection([$procA, $procB]));

        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository
            ->shouldReceive('first')
            ->once()
            ->with(['ip_address' => '192.168.1.12'], 'processes')
            ->andReturn($raspi);

        $workerRepository = Mockery::mock(WorkerRepository::class);
        $workerRepository
            ->shouldReceive('first')
            ->once()
            ->with(['mac_address' => 'AA:BB:EE'], 'processes')
            ->andReturn(null);

        $service = $this->makeService(
            raspberryPi: $raspberryPiRepository,
            worker: $workerRepository,
        );

        $this->assertFalse($service->switchPartNumberFromMqtt('192.168.1.12', 'AA:BB:EE', 'barcode-x'));
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
