<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Worker;
use App\Repositories\AbstractRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbstractRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_constructorはモデル未指定時に対象モデルを解決する(): void
    {
        $repository = new TestWorkerRepository();

        $this->assertInstanceOf(Worker::class, $repository->resolvedModel());
    }

    public function test_allは並び順付きで全件取得できる(): void
    {
        Worker::factory()->create(['identification_number' => 'worker-b', 'worker_name' => 'B']);
        Worker::factory()->create(['identification_number' => 'worker-a', 'worker_name' => 'A']);

        $repository = new TestWorkerRepository();
        $workers = $repository->all(order: 'identification_number');

        $this->assertCount(2, $workers);
        $this->assertSame(['worker-a', 'worker-b'], $workers->pluck('identification_number')->all());
    }

    public function test_findとfirstとgetは条件に一致するモデルを返す(): void
    {
        $worker1 = Worker::factory()->create(['identification_number' => 'worker-001']);
        $worker2 = Worker::factory()->create(['identification_number' => 'worker-002']);

        $repository = new TestWorkerRepository();

        $this->assertTrue($worker1->is($repository->find($worker1->worker_id)));
        $this->assertTrue($worker2->is($repository->first(['identification_number' => 'worker-002'])));

        $workers = $repository->get(['worker_name' => $worker2->worker_name]);

        $this->assertCount(1, $workers);
        $this->assertTrue($worker2->is($workers->first()));
    }

    public function test_storeはリクエスト内容でモデルを新規作成する(): void
    {
        $repository = new TestWorkerRepository();
        $request = $this->makeRequest([
            'identification_number' => 'worker-store-001',
            'worker_name' => 'Stored Worker',
            'mac_address' => '00:11:22:33:44:55',
        ]);

        $result = $repository->store($request);

        $this->assertTrue($result);
        $this->assertDatabaseHas('workers', [
            'identification_number' => 'worker-store-001',
            'worker_name' => 'Stored Worker',
            'mac_address' => '00:11:22:33:44:55',
        ]);
    }

    public function test_updateはリクエスト内容でモデルを更新する(): void
    {
        $worker = Worker::factory()->create([
            'identification_number' => 'worker-update-before',
            'worker_name' => 'Before',
            'mac_address' => '11:22:33:44:55:66',
        ]);
        $repository = new TestWorkerRepository();
        $request = $this->makeRequest([
            'identification_number' => 'worker-update-after',
            'worker_name' => 'After',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ]);

        $result = $repository->update($request, $worker);

        $this->assertTrue($result);
        $this->assertDatabaseHas('workers', [
            'worker_id' => $worker->worker_id,
            'identification_number' => 'worker-update-after',
            'worker_name' => 'After',
            'mac_address' => 'AA:BB:CC:DD:EE:FF',
        ]);
    }

    public function test_destroyはモデルを削除する(): void
    {
        $worker = Worker::factory()->create();
        $repository = new TestWorkerRepository();

        $result = $repository->destroy($worker);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('workers', ['worker_id' => $worker->worker_id]);
    }

    public function test_updateModelはbuilder更新でも真偽値を返す(): void
    {
        $worker = Worker::factory()->create([
            'identification_number' => 'worker-builder-before',
            'worker_name' => 'Before',
        ]);
        $repository = new TestWorkerRepository();

        $result = $repository->exposedUpdateModel(
            Worker::query()->where('worker_id', $worker->worker_id),
            ['worker_name' => 'After Builder Update']
        );

        $this->assertTrue($result);
        $this->assertDatabaseHas('workers', [
            'worker_id' => $worker->worker_id,
            'worker_name' => 'After Builder Update',
        ]);
    }

    private function makeRequest(array $data): FormRequest
    {
        $request = new class extends FormRequest {};
        $request->replace($data);

        return $request;
    }
}

/**
 * @extends AbstractRepository<Worker>
 */
class TestWorkerRepository extends AbstractRepository
{
    public function model(): string
    {
        return Worker::class;
    }

    public function resolvedModel(): Worker
    {
        return $this->model;
    }

    public function exposedUpdateModel(Worker|Builder $obj, array $attributes = []): bool
    {
        return $this->updateModel($obj, $attributes);
    }
}
