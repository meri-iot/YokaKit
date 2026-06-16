<?php

declare(strict_types=1);

namespace Tests\Unit\Queue;

use App\Queue\OrderedDatabaseQueue;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Queue\Jobs\DatabaseJobRecord;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class OrderedDatabaseQueueTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /**
     * 遅延指定の整数はミリ秒として実行可能時刻へ変換する。
     */
    public function test_available_atは整数遅延をミリ秒として扱う(): void
    {
        $now = Carbon::parse('2026-04-09 12:00:00.000');
        Carbon::setTestNow($now);

        $queue = $this->makeQueue();

        $this->assertSame($now->copy()->addRealMilliseconds(1500)->getTimestampMs(), $queue->availableAtForTest(1500));
    }

    /**
     * 同じ実行可能時刻のジョブは古い投入順に取り出す。
     */
    public function test_get_next_available_jobは同時刻なら古いジョブを優先する(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-09 12:00:00.000'));

        $firstJobId = $this->insertJob('default', 1_744_199_200_000, 'first');
        $secondJobId = $this->insertJob('default', 1_744_199_200_000, 'second');

        $job = $this->makeQueue()->getNextAvailableJobForTest('default');

        $this->assertInstanceOf(DatabaseJobRecord::class, $job);
        $this->assertSame($firstJobId, $job->id);
        $this->assertNotSame($secondJobId, $job->id);
    }

    /**
     * 未来のジョブよりも既に実行可能なジョブを優先する。
     */
    public function test_get_next_available_jobは最も早く実行可能なジョブを返す(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-04-09 12:00:00.000'));

        $readyJobId = $this->insertJob('default', 1_744_199_199_500, 'ready');
        $this->insertJob('default', 1_744_199_205_000, 'future');

        $job = $this->makeQueue()->getNextAvailableJobForTest('default');

        $this->assertInstanceOf(DatabaseJobRecord::class, $job);
        $this->assertSame($readyJobId, $job->id);
    }

    /**
     * テスト対象の protected メソッドを公開したキューを構築する。
     */
    private function makeQueue(): TestOrderedDatabaseQueue
    {
        return new TestOrderedDatabaseQueue(
            DB::connection(),
            'jobs',
            'default',
            60,
        );
    }

    /**
     * jobs テーブルへテスト用ジョブを追加する。
     */
    private function insertJob(string $queue, int $availableAt, string $name): int
    {
        return (int) DB::table('jobs')->insertGetId([
            'queue' => $queue,
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $availableAt,
            'created_at' => 1_744_199_200,
            'payload' => json_encode(['displayName' => $name], JSON_THROW_ON_ERROR),
        ]);
    }
}

class TestOrderedDatabaseQueue extends OrderedDatabaseQueue
{
    public function availableAtForTest(DateTimeInterface|\DateInterval|int $delay = 0): int
    {
        return $this->availableAt($delay);
    }

    public function getNextAvailableJobForTest(?string $queue): ?DatabaseJobRecord
    {
        return $this->getNextAvailableJob($queue);
    }
}
