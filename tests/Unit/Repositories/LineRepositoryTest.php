<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\Line;
use App\Models\Process;
use App\Models\RaspberryPi;
use App\Models\Worker;
use App\Repositories\LineRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LineRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_modelはLineクラスを返す(): void
    {
        $repository = new LineRepository();

        $this->assertSame(Line::class, $repository->model());
    }

    public function test_nonDefectiveOptionsは対象工程の非不良ラインのみを選択肢で返す(): void
    {
        $process = Process::factory()->create();
        $otherProcess = Process::factory()->create();

        $lineA = $this->createLine($process, 31, 0, null, false);
        $lineB = $this->createLine($process, 32, 1, null, false);
        $this->createLine($process, 33, 2, null, true);       // 除外: 不良ライン
        $this->createLine($otherProcess, 34, 0, null, false); // 除外: 別工程

        $repository = new LineRepository();

        $actual = $repository->nonDefectiveOptions($process->process_id);

        $this->assertSame([
            '' => '',
            $lineA->line_id => $lineA->line_name,
            $lineB->line_id => $lineB->line_name,
        ], $actual);
    }

    public function test_nonDefectiveOptionsは対象がない場合でも空選択肢を返す(): void
    {
        $process = Process::factory()->create();
        $this->createLine($process, 40, 0, null, true);

        $repository = new LineRepository();

        $actual = $repository->nonDefectiveOptions($process->process_id);

        $this->assertSame(['' => ''], $actual);
    }

    public function test_sortは指定順に並べ替える(): void
    {
        $process = Process::factory()->create();
        $first  = $this->createLine($process, 1, 10);
        $second = $this->createLine($process, 2, 11);
        $third  = $this->createLine($process, 3, 12);
        $repository = new LineRepository();

        $repository->sort($process->process_id, [
            $third->line_id,
            $first->line_id,
            $second->line_id,
        ]);

        $this->assertSame(0, Line::query()->findOrFail($third->line_id)->order);
        $this->assertSame(1, Line::query()->findOrFail($first->line_id)->order);
        $this->assertSame(2, Line::query()->findOrFail($second->line_id)->order);
    }

    public function test_sortは既存順と同じ指定でも例外を投げない(): void
    {
        $process = Process::factory()->create();
        $first  = $this->createLine($process, 4, 0);
        $second = $this->createLine($process, 5, 1);
        $repository = new LineRepository();

        // 同じ順序で呼んでも例外にならないことを確認する。
        // (order 変化なし → DB 更新 0 件であっても正常扱いでなければならない)
        $repository->sort($process->process_id, [
            $first->line_id,
            $second->line_id,
        ]);

        $this->assertSame(0, Line::query()->findOrFail($first->line_id)->order);
        $this->assertSame(1, Line::query()->findOrFail($second->line_id)->order);
    }

    public function test_sortは対象工程に存在しないIDが含まれると例外を投げてロールバックする(): void
    {
        $process      = Process::factory()->create();
        $otherProcess = Process::factory()->create();
        $first  = $this->createLine($process, 6, 0);
        $second = $this->createLine($process, 7, 1);
        $other  = $this->createLine($otherProcess, 8, 0);
        $repository = new LineRepository();

        $this->expectException(ModelNotFoundException::class);

        try {
            $repository->sort($process->process_id, [
                $second->line_id,
                $other->line_id,  // 別工程のID → 見つからず例外
                $first->line_id,
            ]);
        } finally {
            // 途中で更新されてもトランザクションでロールバックされることを確認する。
            $this->assertSame(0, Line::query()->findOrFail($first->line_id)->order);
            $this->assertSame(1, Line::query()->findOrFail($second->line_id)->order);
        }
    }

    public function test_updateWorkerは作業者を更新する(): void
    {
        $process = Process::factory()->create();
        $line    = $this->createLine($process, 9, 0);
        $worker  = Worker::factory()->create();
        $repository = new LineRepository();

        $result = $repository->updateWorker($line->line_id, $worker->worker_id);

        $this->assertTrue($result);
        $this->assertSame($worker->worker_id, Line::query()->findOrFail($line->line_id)->worker_id);
    }

    public function test_updateWorkerはnullで作業者を解除する(): void
    {
        $process = Process::factory()->create();
        $worker  = Worker::factory()->create();
        $line    = $this->createLine($process, 10, 0, $worker->worker_id);
        $repository = new LineRepository();

        $result = $repository->updateWorker($line->line_id, null);

        $this->assertTrue($result);
        $this->assertNull(Line::query()->findOrFail($line->line_id)->worker_id);
    }

    public function test_updateWorkerは存在しないラインIDでfalseを返す(): void
    {
        $repository = new LineRepository();

        $result = $repository->updateWorker(PHP_INT_MAX, null);

        $this->assertFalse($result);
    }

    // ────────────────────────────────────────────────────────────
    // ヘルパー
    // ────────────────────────────────────────────────────────────

    /**
     * テスト用ラインを作成する。
     *
     * pi_number は raspberry_pi ごとに一意制約があるため、呼び出しごとに
     * 新しい RaspberryPi を生成して制約違反を避ける。
     *
     * @param Process  $process   対象工程
     * @param int      $pinNumber ピン番号
     * @param int      $order     並び順
     * @param int|null $workerId  作業者ID (省略時は null)
     */
    private function createLine(Process $process, int $pinNumber, int $order, ?int $workerId = null, bool $defective = false): Line
    {
        $pi = RaspberryPi::factory()->create();

        /** @var Line */
        return Line::query()->create([
            'process_id'      => $process->process_id,
            'raspberry_pi_id' => $pi->raspberry_pi_id,
            'pin_number'      => $pinNumber,
            'line_name'       => "line-{$process->process_id}-{$pinNumber}",
            'chart_color'     => '#123456',
            'order'           => $order,
            'worker_id'       => $workerId,
            'defective'       => $defective,
        ]);
    }
}
