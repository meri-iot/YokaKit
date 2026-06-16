<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Models\PlannedOutage;
use App\Models\ProductionHistory;
use App\Models\ProductionPlannedOutage;
use App\Repositories\ProductionPlannedOutageRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionPlannedOutageRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_modelはProductionPlannedOutageクラスを返す(): void
    {
        $repository = new ProductionPlannedOutageRepository();

        $this->assertSame(ProductionPlannedOutage::class, $repository->model());
    }

    public function test_saveは生産時の計画停止時間を保存する(): void
    {
        $history = ProductionHistory::factory()->create();
        $plannedOutage = PlannedOutage::factory()->create([
            'planned_outage_name' => 'lunch',
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
        ]);
        $repository = new ProductionPlannedOutageRepository();

        $result = $repository->save($plannedOutage, $history->production_history_id);

        $this->assertTrue($result);
        $this->assertDatabaseHas('production_planned_outages', [
            'production_history_id' => $history->production_history_id,
            'planned_outage_name' => 'lunch',
            'start_time' => '12:00:00',
            'end_time' => '13:00:00',
        ]);
    }

    public function test_getStartEndAsArrayは同日区間を開始時刻順で返す(): void
    {
        $history = ProductionHistory::factory()->create();
        $this->createProductionPlannedOutage($history->production_history_id, 'break-2', '12:00:00', '12:30:00');
        $this->createProductionPlannedOutage($history->production_history_id, 'break-1', '10:00:00', '10:30:00');
        $repository = new ProductionPlannedOutageRepository();

        $result = $repository->getStartEndAsArray($history->production_history_id);

        $this->assertSame([
            ['startTime' => '10:00:00', 'endTime' => '10:30:00'],
            ['startTime' => '12:00:00', 'endTime' => '12:30:00'],
        ], $result);
    }

    public function test_getStartEndAsArrayは重複区間をマージする(): void
    {
        $history = ProductionHistory::factory()->create();
        $this->createProductionPlannedOutage($history->production_history_id, 'break-a', '10:00:00', '12:00:00');
        $this->createProductionPlannedOutage($history->production_history_id, 'break-b', '11:00:00', '13:00:00');
        $repository = new ProductionPlannedOutageRepository();

        $result = $repository->getStartEndAsArray($history->production_history_id);

        $this->assertSame([
            ['startTime' => '10:00:00', 'endTime' => '13:00:00'],
        ], $result);
    }

    public function test_getStartEndAsArrayは日付またぎ区間を00時境界で分割する(): void
    {
        $history = ProductionHistory::factory()->create();
        $this->createProductionPlannedOutage($history->production_history_id, 'night', '23:00:00', '01:00:00');
        $repository = new ProductionPlannedOutageRepository();

        $result = $repository->getStartEndAsArray($history->production_history_id);

        $this->assertSame([
            ['startTime' => '00:00:00', 'endTime' => '01:00:00'],
            ['startTime' => '23:00:00', 'endTime' => '00:00:00'],
        ], $result);
    }

    public function test_getStartEndAsArrayは対象データがなければ空配列を返す(): void
    {
        $history = ProductionHistory::factory()->create();
        $repository = new ProductionPlannedOutageRepository();

        $result = $repository->getStartEndAsArray($history->production_history_id);

        $this->assertSame([], $result);
    }

    private function createProductionPlannedOutage(int $historyId, string $name, string $start, string $end): ProductionPlannedOutage
    {
        /** @var ProductionPlannedOutage */
        return ProductionPlannedOutage::query()->create([
            'production_history_id' => $historyId,
            'planned_outage_name' => $name,
            'start_time' => $start,
            'end_time' => $end,
        ]);
    }
}
