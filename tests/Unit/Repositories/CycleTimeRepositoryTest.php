<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\ProductionStatus;
use App\Models\CycleTime;
use App\Models\PartNumber;
use App\Models\Process;
use App\Models\ProductionHistory;
use App\Repositories\CycleTimeRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CycleTimeRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_modelはCycleTimeクラスを返す(): void
    {
        $repository = new CycleTimeRepository();

        $this->assertSame(CycleTime::class, $repository->model());
    }

    public function test_notRunningPartNumberOptionsは未稼働時に対象工程の全品番を返す(): void
    {
        $process = Process::factory()->create();
        $partNumberA = PartNumber::factory()->create(['part_number_name' => 'PN-A']);
        $partNumberB = PartNumber::factory()->create(['part_number_name' => 'PN-B']);
        $otherProcess = Process::factory()->create();
        $otherPartNumber = PartNumber::factory()->create(['part_number_name' => 'PN-OTHER']);

        $this->createCycleTime($process, $partNumberA);
        $this->createCycleTime($process, $partNumberB);
        $this->createCycleTime($otherProcess, $otherPartNumber);

        $repository = new CycleTimeRepository();
        $options = $repository->notRunningPartNumberOptions($process);

        $this->assertSame([
            $partNumberA->part_number_id => 'PN-A',
            $partNumberB->part_number_id => 'PN-B',
        ], $options);
    }

    public function test_notRunningPartNumberOptionsは稼働中品番を除外する(): void
    {
        $process = Process::factory()->create();
        $runningPartNumber = PartNumber::factory()->create(['part_number_name' => 'PN-RUNNING']);
        $otherPartNumber = PartNumber::factory()->create(['part_number_name' => 'PN-OTHER']);
        $productionHistory = ProductionHistory::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $runningPartNumber->part_number_id,
            'part_number_name' => 'PN-RUNNING',
            'process_name' => $process->process_name,
            'plan_color' => $process->plan_color,
            'count_switch' => false,
            'status' => ProductionStatus::RUNNING(),
        ]);
        $process->update(['production_history_id' => $productionHistory->production_history_id]);
        $process->refresh();

        $this->createCycleTime($process, $runningPartNumber);
        $this->createCycleTime($process, $otherPartNumber);

        $repository = new CycleTimeRepository();
        $options = $repository->notRunningPartNumberOptions($process);

        $this->assertSame([
            $otherPartNumber->part_number_id => 'PN-OTHER',
        ], $options);
    }

    public function test_notRunningPartNumberOptionsは対象工程以外の品番を含めない(): void
    {
        $process = Process::factory()->create();
        $otherProcess = Process::factory()->create();
        $partNumber = PartNumber::factory()->create(['part_number_name' => 'PN-TARGET']);
        $otherPartNumber = PartNumber::factory()->create(['part_number_name' => 'PN-OTHER']);

        $this->createCycleTime($process, $partNumber);
        $this->createCycleTime($otherProcess, $otherPartNumber);

        $repository = new CycleTimeRepository();
        $options = $repository->notRunningPartNumberOptions($process);

        $this->assertSame([
            $partNumber->part_number_id => 'PN-TARGET',
        ], $options);
    }

    private function createCycleTime(Process $process, PartNumber $partNumber): CycleTime
    {
        return CycleTime::factory()->create([
            'process_id' => $process->process_id,
            'part_number_id' => $partNumber->part_number_id,
        ]);
    }
}
