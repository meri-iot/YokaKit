<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Http\Requests\SortLineRequest;
use App\Http\Requests\StoreLineRequest;
use App\Http\Requests\UpdateLineRequest;
use App\Models\Line;
use App\Models\Process;
use App\Repositories\LineRepository;
use App\Repositories\RaspberryPiRepository;
use App\Repositories\WorkerRepository;
use App\Services\LineService;
use Mockery;
use Tests\TestCase;

class LineServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_nonDefectiveLineOptionsはリポジトリの選択肢を返す(): void
    {
        $process = new Process();
        $process->process_id = 12;

        $lineRepository = Mockery::mock(LineRepository::class);
        $lineRepository
            ->shouldReceive('nonDefectiveOptions')
            ->once()
            ->with(12)
            ->andReturn(['' => '', 3 => 'Main', 5 => 'Sub']);

        $service = new LineService(
            $lineRepository,
            Mockery::mock(RaspberryPiRepository::class),
            Mockery::mock(WorkerRepository::class),
        );

        $this->assertSame(['' => '', 3 => 'Main', 5 => 'Sub'], $service->nonDefectiveLineOptions($process));
    }

    public function test_raspberryPiOptionsはリポジトリへ委譲する(): void
    {
        $raspberryPiRepository = Mockery::mock(RaspberryPiRepository::class);
        $raspberryPiRepository
            ->shouldReceive('options')
            ->once()
            ->andReturn([1 => 'Pi-A : 192.168.0.10']);

        $service = new LineService(
            Mockery::mock(LineRepository::class),
            $raspberryPiRepository,
            Mockery::mock(WorkerRepository::class),
        );

        $this->assertSame([1 => 'Pi-A : 192.168.0.10'], $service->raspberryPiOptions());
    }

    public function test_workerOptionsはリポジトリへ委譲する(): void
    {
        $workerRepository = Mockery::mock(WorkerRepository::class);
        $workerRepository
            ->shouldReceive('options')
            ->once()
            ->andReturn(['' => '', 1 => 'W-001 : Yamada']);

        $service = new LineService(
            Mockery::mock(LineRepository::class),
            Mockery::mock(RaspberryPiRepository::class),
            $workerRepository,
        );

        $this->assertSame(['' => '', 1 => 'W-001 : Yamada'], $service->workerOptions());
    }

    public function test_storeはリポジトリへ委譲する(): void
    {
        $request = Mockery::mock(StoreLineRequest::class);

        $lineRepository = Mockery::mock(LineRepository::class);
        $lineRepository
            ->shouldReceive('store')
            ->once()
            ->with($request)
            ->andReturn(true);

        $service = new LineService(
            $lineRepository,
            Mockery::mock(RaspberryPiRepository::class),
            Mockery::mock(WorkerRepository::class),
        );

        $this->assertTrue($service->store($request));
    }

    public function test_updateはリポジトリへ委譲する(): void
    {
        $request = Mockery::mock(UpdateLineRequest::class);
        $line = new Line();
        $line->line_id = 20;

        $lineRepository = Mockery::mock(LineRepository::class);
        $lineRepository
            ->shouldReceive('update')
            ->once()
            ->with($request, $line)
            ->andReturn(true);

        $service = new LineService(
            $lineRepository,
            Mockery::mock(RaspberryPiRepository::class),
            Mockery::mock(WorkerRepository::class),
        );

        $this->assertTrue($service->update($request, $line));
    }

    public function test_destroyはリポジトリへ委譲する(): void
    {
        $line = new Line();
        $line->line_id = 21;

        $lineRepository = Mockery::mock(LineRepository::class);
        $lineRepository
            ->shouldReceive('destroy')
            ->once()
            ->with($line)
            ->andReturn(true);

        $service = new LineService(
            $lineRepository,
            Mockery::mock(RaspberryPiRepository::class),
            Mockery::mock(WorkerRepository::class),
        );

        $this->assertTrue($service->destroy($line));
    }

    public function test_sortはリポジトリへ委譲する(): void
    {
        $request = Mockery::mock(SortLineRequest::class);
        $request->order = [9, 2, 4];

        $process = new Process();
        $process->process_id = 8;

        $lineRepository = Mockery::mock(LineRepository::class);
        $lineRepository
            ->shouldReceive('sort')
            ->once()
            ->with(8, [9, 2, 4]);

        $service = new LineService(
            $lineRepository,
            Mockery::mock(RaspberryPiRepository::class),
            Mockery::mock(WorkerRepository::class),
        );

        $service->sort($request, $process);

        $this->assertTrue(true);
    }
}
