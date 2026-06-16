<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Producer;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use App\Models\Worker;
use App\Repositories\ProducerRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProducerRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_modelはProducerクラスを返す(): void
    {
        $repository = new ProducerRepository();

        $this->assertSame(Producer::class, $repository->model());
    }

    public function test_saveは生産者を保存する(): void
    {
        $worker = $this->createWorker('W001', 'worker-a');
        $productionLine = $this->createProductionLine('line-a');
        $at = Carbon::parse('2026-04-09 09:00:00');
        $repository = new ProducerRepository();

        $result = $repository->save($worker, $productionLine->production_line_id, $at);

        $this->assertTrue($result);
        $this->assertDatabaseHas('producers', [
            'worker_id' => $worker->worker_id,
            'production_line_id' => $productionLine->production_line_id,
            'identification_number' => 'W001',
            'worker_name' => 'worker-a',
            'start' => '2026-04-09 09:00:00',
            'stop' => null,
        ]);
    }

    public function test_stopは単一の生産者を停止する(): void
    {
        $worker = $this->createWorker('W002', 'worker-b');
        $productionLine = $this->createProductionLine('line-b');
        $producer = $this->createProducer($worker, $productionLine, '2026-04-09 10:00:00', null);
        $at = Carbon::parse('2026-04-09 12:00:00');
        $repository = new ProducerRepository();

        $repository->stop($producer, $at);

        $stopped = $producer->fresh();
        $this->assertSame('2026-04-09 12:00:00', $stopped?->stop?->format('Y-m-d H:i:s'));
    }

    public function test_stopは生産ラインコレクションに紐づく稼働中生産者のみ停止する(): void
    {
        $workerA = $this->createWorker('W003', 'worker-c');
        $workerB = $this->createWorker('W004', 'worker-d');
        $lineA = $this->createProductionLine('line-c');
        $lineB = $this->createProductionLine('line-d');

        $targetOpen = $this->createProducer($workerA, $lineA, '2026-04-09 08:00:00', null);
        $targetClosed = $this->createProducer($workerA, $lineA, '2026-04-09 06:00:00', '2026-04-09 07:00:00');
        $otherOpen = $this->createProducer($workerB, $lineB, '2026-04-09 08:00:00', null);

        $at = Carbon::parse('2026-04-09 13:00:00');
        $repository = new ProducerRepository();

        $repository->stop(new Collection([$lineA]), $at);

        $this->assertSame('2026-04-09 13:00:00', $targetOpen->fresh()?->stop?->format('Y-m-d H:i:s'));
        $this->assertSame('2026-04-09 07:00:00', $targetClosed->fresh()?->stop?->format('Y-m-d H:i:s'));
        $this->assertNull($otherOpen->fresh()?->stop);
    }

    public function test_stopは空コレクションの場合は何もしない(): void
    {
        $worker = $this->createWorker('W005', 'worker-e');
        $line = $this->createProductionLine('line-e');
        $producer = $this->createProducer($worker, $line, '2026-04-09 08:00:00', null);
        $repository = new ProducerRepository();

        $repository->stop(new Collection(), Carbon::parse('2026-04-09 15:00:00'));

        $this->assertNull($producer->fresh()?->stop);
    }

    public function test_findByは指定ラインの稼働中生産者を返す(): void
    {
        $worker = $this->createWorker('W006', 'worker-f');
        $line = $this->createProductionLine('line-f');
        $open = $this->createProducer($worker, $line, '2026-04-09 08:00:00', null);
        $this->createProducer($worker, $line, '2026-04-09 06:00:00', '2026-04-09 07:00:00');
        $repository = new ProducerRepository();

        $found = $repository->findBy($line->production_line_id);

        $this->assertNotNull($found);
        $this->assertSame($open->producer_id, $found?->producer_id);
    }

    public function test_findByは稼働中生産者がいなければnullを返す(): void
    {
        $worker = $this->createWorker('W007', 'worker-g');
        $line = $this->createProductionLine('line-g');
        $this->createProducer($worker, $line, '2026-04-09 06:00:00', '2026-04-09 07:00:00');
        $repository = new ProducerRepository();

        $found = $repository->findBy($line->production_line_id);

        $this->assertNull($found);
    }

    private function createWorker(string $number, string $name): Worker
    {
        /** @var Worker */
        return Worker::query()->create([
            'identification_number' => $number,
            'worker_name' => $name,
            'mac_address' => null,
        ]);
    }

    private function createProductionLine(string $lineName): ProductionLine
    {
        $history = ProductionHistory::factory()->create();

        /** @var ProductionLine */
        return ProductionLine::query()->create([
            'production_history_id' => $history->production_history_id,
            'line_id' => null,
            'parent_id' => null,
            'line_name' => $lineName,
            'chart_color' => '#112233',
            'ip_address' => '192.168.10.10',
            'pin_number' => 1,
            'defective' => false,
            'order' => 1,
            'indicator' => false,
            'offset_count' => null,
            'count' => 0,
        ]);
    }

    private function createProducer(Worker $worker, ProductionLine $line, string $start, ?string $stop): Producer
    {
        /** @var Producer */
        return Producer::query()->create([
            'worker_id' => $worker->worker_id,
            'production_line_id' => $line->production_line_id,
            'identification_number' => $worker->identification_number,
            'worker_name' => $worker->worker_name,
            'start' => $start,
            'stop' => $stop,
        ]);
    }
}
