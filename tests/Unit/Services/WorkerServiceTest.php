<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Http\Requests\StoreWorkerRequest;
use App\Http\Requests\UpdateWorkerRequest;
use App\Models\Worker;
use App\Repositories\WorkerRepository;
use App\Services\WorkerService;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Mockery;
use Tests\TestCase;

class WorkerServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_allはリポジトリへ委譲する(): void
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

        $service = new WorkerService($workerRepository);

        $actual = $service->all();

        $this->assertCount(2, $actual);
    }

    public function test_storeはリポジトリへ委譲する(): void
    {
        $request = Mockery::mock(StoreWorkerRequest::class);

        $workerRepository = Mockery::mock(WorkerRepository::class);
        $workerRepository
            ->shouldReceive('store')
            ->once()
            ->with($request)
            ->andReturn(true);

        $service = new WorkerService($workerRepository);

        $this->assertTrue($service->store($request));
    }

    public function test_updateはリポジトリへ委譲する(): void
    {
        $request = Mockery::mock(UpdateWorkerRequest::class);
        $worker = new Worker();
        $worker->worker_id = 10;

        $workerRepository = Mockery::mock(WorkerRepository::class);
        $workerRepository
            ->shouldReceive('update')
            ->once()
            ->with($request, $worker)
            ->andReturn(true);

        $service = new WorkerService($workerRepository);

        $this->assertTrue($service->update($request, $worker));
    }

    public function test_destroyはリポジトリへ委譲する(): void
    {
        $worker = new Worker();
        $worker->worker_id = 11;

        $workerRepository = Mockery::mock(WorkerRepository::class);
        $workerRepository
            ->shouldReceive('destroy')
            ->once()
            ->with($worker)
            ->andReturn(true);

        $service = new WorkerService($workerRepository);

        $this->assertTrue($service->destroy($worker));
    }
}
