<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\ProductionStatus;
use App\Models\CycleTime;
use App\Models\PartNumber;
use App\Models\Process;
use App\Models\ProductionHistory;
use App\Repositories\ProductionHistoryRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductionHistoryRepositoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_modelはProductionHistoryクラスを返す(): void
    {
        $repository = new ProductionHistoryRepository();

        $this->assertSame(ProductionHistory::class, $repository->model());
    }

    public function test_updateStatusは生産ステータスを更新する(): void
    {
        $history = ProductionHistory::factory()->create([
            'status' => ProductionStatus::RUNNING(),
        ]);
        $repository = new ProductionHistoryRepository();

        $repository->updateStatus($history, ProductionStatus::BREAKDOWN());

        $this->assertTrue($history->fresh()->status->is(ProductionStatus::BREAKDOWN()));
    }

    public function test_storeHistoryは工程とサイクルタイムから履歴を登録する(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 9, 10, 11, 12, config('app.timezone'))->microseconds(123000));
        $process = Process::factory()->create([
            'process_name' => '工程A',
            'plan_color' => '#112233',
            'count_switch' => true,
        ]);
        $cycleTime = $this->createCycleTime($process, 'PN-001', 45.5, 90.25);
        $repository = new ProductionHistoryRepository();

        $history = $repository->storeHistory($process, $cycleTime, ProductionStatus::RUNNING(), 120);

        $this->assertNotNull($history);
        $this->assertSame($process->process_id, $history->process_id);
        $this->assertSame($cycleTime->part_number_id, $history->part_number_id);
        $this->assertSame('工程A', $history->process_name);
        $this->assertSame('#112233', $history->plan_color);
        $this->assertTrue($history->count_switch);
        $this->assertSame('PN-001', $history->part_number_name);
        $this->assertSame(45.5, (float) $history->cycle_time);
        $this->assertSame(90.25, (float) $history->over_time);
        $this->assertSame(120, $history->goal);
        $this->assertSame('2026-04-09 10:11:12', $history->start->format('Y-m-d H:i:s'));
        $this->assertTrue($history->status->is(ProductionStatus::RUNNING()));
    }

    public function test_stopは停止時刻と完了ステータスを保存する(): void
    {
        $history = ProductionHistory::factory()->create([
            'status' => ProductionStatus::RUNNING(),
            'stop' => null,
        ]);
        $stopAt = Carbon::create(2026, 4, 9, 12, 34, 56, config('app.timezone'));
        $repository = new ProductionHistoryRepository();

        $result = $repository->stop($history, $stopAt);

        $this->assertTrue($result);
        $this->assertSame('2026-04-09 12:34:56', $history->fresh()->stop?->format('Y-m-d H:i:s'));
        $this->assertTrue($history->fresh()->status->is(ProductionStatus::COMPLETE()));
    }

    public function test_historiesは工程品番日付で絞り込み開始日時の降順で返す(): void
    {
        $process = Process::factory()->create();
        $otherProcess = Process::factory()->create();
        $includedOld = $this->createHistory($process, 'PN-A', '2026-04-08 09:00:00', '2026-04-08 10:00:00');
        $includedNew = $this->createHistory($process, 'PN-A', '2026-04-09 09:00:00', '2026-04-09 10:00:00');
        $this->createHistory($process, 'PN-B', '2026-04-09 08:00:00', '2026-04-09 09:00:00');
        $this->createHistory($otherProcess, 'PN-A', '2026-04-09 11:00:00', '2026-04-09 12:00:00');
        $repository = new ProductionHistoryRepository();

        $histories = $repository->histories($process->process_id, 'PN-A', '2026-04-08', '2026-04-09', 10);

        $this->assertInstanceOf(LengthAwarePaginator::class, $histories);
        $this->assertSame([
            $includedNew->production_history_id,
            $includedOld->production_history_id,
        ], collect($histories->items())->pluck('production_history_id')->all());
    }

    public function test_historiesは空文字の日付入力で直近7日へフォールバックする(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 9, 12, 0, 0, config('app.timezone')));
        $process = Process::factory()->create();
        $recent = $this->createHistory($process, 'PN-A', '2026-04-03 08:00:00', '2026-04-03 09:00:00');
        $this->createHistory($process, 'PN-A', '2026-04-02 08:00:00', '2026-04-02 09:00:00');
        $repository = new ProductionHistoryRepository();

        $histories = $repository->histories($process->process_id, null, '', '', 10);

        $this->assertSame([
            $recent->production_history_id,
        ], collect($histories->items())->pluck('production_history_id')->all());
    }

    public function test_historiesは不正な日付入力でも直近7日へフォールバックする(): void
    {
        Carbon::setTestNow(Carbon::create(2026, 4, 9, 12, 0, 0, config('app.timezone')));
        $process = Process::factory()->create();
        $recent = $this->createHistory($process, 'PN-A', '2026-04-05 08:00:00', '2026-04-05 09:00:00');
        $this->createHistory($process, 'PN-A', '2026-03-31 08:00:00', '2026-03-31 09:00:00');
        $repository = new ProductionHistoryRepository();

        $histories = $repository->histories($process->process_id, null, 'invalid-date', '2026-99-99', 10);

        $this->assertSame([
            $recent->production_history_id,
        ], collect($histories->items())->pluck('production_history_id')->all());
    }

    public function test_softDeleteは指定した履歴を論理削除する(): void
    {
        $first = ProductionHistory::factory()->create();
        $second = ProductionHistory::factory()->create();
        $repository = new ProductionHistoryRepository();

        $deleted = $repository->softDelete([
            $first->production_history_id,
            $second->production_history_id,
        ]);

        $this->assertSame(2, $deleted);
        $this->assertSoftDeleted($first);
        $this->assertSoftDeleted($second);
    }

    private function createCycleTime(Process $process, string $partNumberName, float $cycleTime, float $overTime): CycleTime
    {
        $partNumber = PartNumber::factory()->create([
            'part_number_name' => $partNumberName,
        ]);

        /** @var CycleTime $created */
        $created = CycleTime::query()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
            'cycle_time' => $cycleTime,
            'over_time' => $overTime,
        ]);

        return $created->load('partNumber');
    }

    private function createHistory(Process $process, string $partNumberName, string $start, string $stop): ProductionHistory
    {
        return ProductionHistory::factory()->create([
            'process_id' => $process->process_id,
            'process_name' => $process->process_name,
            'part_number_name' => $partNumberName,
            'count_switch' => false,
            'start' => Carbon::parse($start, config('app.timezone')),
            'stop' => Carbon::parse($stop, config('app.timezone')),
            'status' => ProductionStatus::COMPLETE(),
        ]);
    }
}
