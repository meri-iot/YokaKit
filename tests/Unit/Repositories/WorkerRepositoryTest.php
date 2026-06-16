<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Worker;
use App\Repositories\WorkerRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class WorkerRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_modelはWorkerクラスを返す(): void
    {
        $repository = new WorkerRepository();

        $this->assertSame(Worker::class, $repository->model());
    }

    public function test_optionsは空要素付きで識別番号順の選択肢を返す(): void
    {
        $workerB = Worker::factory()->create([
            'identification_number' => 'B002',
            'worker_name' => 'worker-b',
        ]);
        $workerA = Worker::factory()->create([
            'identification_number' => 'A001',
            'worker_name' => 'worker-a',
        ]);
        $repository = new WorkerRepository();

        $options = $repository->options();

        $this->assertSame([
            '' => '',
            $workerA->worker_id => 'A001 : worker-a',
            $workerB->worker_id => 'B002 : worker-b',
        ], $options);
    }

    public function test_storeは作業者を保存する(): void
    {
        $request = $this->mockRequest([
            'identification_number' => 'W100',
            'worker_name' => 'worker-store',
            'mac_address' => null,
        ]);
        $repository = new WorkerRepository();

        $result = $repository->store($request);

        $this->assertTrue($result);
        $this->assertDatabaseHas('workers', [
            'identification_number' => 'W100',
            'worker_name' => 'worker-store',
        ]);
    }

    public function test_updateは既存作業者を更新する(): void
    {
        $worker = Worker::factory()->create([
            'identification_number' => 'W101',
            'worker_name' => 'before',
            'mac_address' => null,
        ]);
        $request = $this->mockRequest([
            'identification_number' => 'W201',
            'worker_name' => 'after',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ]);
        $repository = new WorkerRepository();

        $result = $repository->update($request, $worker);

        $this->assertTrue($result);
        $updated = $worker->fresh();
        $this->assertSame('W201', $updated?->identification_number);
        $this->assertSame('after', $updated?->worker_name);
        $this->assertSame('AA:BB:CC:DD:EE:FF', $updated?->mac_address);
    }

    public function test_getは条件に一致する作業者を返す(): void
    {
        $worker = Worker::factory()->create([
            'identification_number' => 'W301',
            'worker_name' => 'target',
        ]);
        Worker::factory()->create([
            'identification_number' => 'W302',
            'worker_name' => 'other',
        ]);
        $repository = new WorkerRepository();

        $result = $repository->get(['identification_number' => 'W301']);

        $this->assertCount(1, $result);
        $this->assertSame($worker->worker_id, $result->first()?->worker_id);
    }

    public function test_destroyは作業者を削除する(): void
    {
        $worker = Worker::factory()->create();
        $repository = new WorkerRepository();

        $result = $repository->destroy($worker);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('workers', [
            'worker_id' => $worker->worker_id,
        ]);
    }

    private function mockRequest(array $data): FormRequest
    {
        $request = Mockery::mock(FormRequest::class);
        $request->shouldReceive('all')->once()->andReturn($data);

        return $request;
    }
}
