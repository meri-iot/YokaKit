<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Data\PayloadData;
use App\Enums\ProductionStatus;
use App\Models\Production;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use App\Repositories\ProductionRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ProductionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_modelはProductionクラスを返す(): void
    {
        $repository = new ProductionRepository();

        $this->assertSame(Production::class, $repository->model());
    }

    public function test_saveは生産データを保存してモデルを返す(): void
    {
        $productionLine = $this->createProductionLine();
        $payloadData = $this->createPayloadData($productionLine->production_line_id);
        $repository = new ProductionRepository();

        $saved = $repository->save($productionLine->production_line_id, $payloadData);

        $this->assertSame($productionLine->production_line_id, $saved->production_line_id);
        $this->assertSame(10, $saved->count);
        $this->assertSame(2, $saved->defective_count);
        $this->assertTrue($saved->status->is(ProductionStatus::RUNNING()));
        $this->assertFalse($saved->in_planned_outage);
        $this->assertDatabaseHas('productions', [
            'production_id' => $saved->production_id,
            'production_line_id' => $productionLine->production_line_id,
            'count' => 10,
            'defective_count' => 2,
            'status' => ProductionStatus::RUNNING,
            'in_planned_outage' => 0,
            'working_time' => 60000,
            'loading_time' => 50000,
            'operating_time' => 40000,
            'net_time' => 30000,
            'breakdown_count' => 1,
            'auto_resume_count' => 3,
        ]);
    }

    public function test_saveは不正な生産ラインIDならQueryExceptionを投げる(): void
    {
        $payloadData = $this->createPayloadData(99999);
        $repository = new ProductionRepository();

        $this->expectException(QueryException::class);

        $repository->save(99999, $payloadData);
    }

    public function test_judgeBreakdownは後続データがなければtrueを返す(): void
    {
        $line = $this->createProductionLine();
        $production = $this->createProduction($line->production_line_id, '2026-04-09 10:00:00.000', 10, ProductionStatus::RUNNING);
        $repository = new ProductionRepository();

        $result = $repository->judgeBreakdown($production, Carbon::parse('2026-04-09 10:05:00.000'));

        $this->assertTrue($result);
    }

    public function test_judgeBreakdownは後続で生産数が増えていればfalseを返す(): void
    {
        $line = $this->createProductionLine();
        $production = $this->createProduction($line->production_line_id, '2026-04-09 10:00:00.000', 10, ProductionStatus::RUNNING);
        $this->createProduction($line->production_line_id, '2026-04-09 10:03:00.000', 11, ProductionStatus::RUNNING);
        $repository = new ProductionRepository();

        $result = $repository->judgeBreakdown($production, Carbon::parse('2026-04-09 10:05:00.000'));

        $this->assertFalse($result);
    }

    public function test_judgeBreakdownは後続でステータス変化があればfalseを返す(): void
    {
        $line = $this->createProductionLine();
        $production = $this->createProduction($line->production_line_id, '2026-04-09 10:00:00.000', 10, ProductionStatus::RUNNING);
        $this->createProduction($line->production_line_id, '2026-04-09 10:03:00.000', 10, ProductionStatus::CHANGEOVER);
        $repository = new ProductionRepository();

        $result = $repository->judgeBreakdown($production, Carbon::parse('2026-04-09 10:05:00.000'));

        $this->assertFalse($result);
    }

    public function test_judgeBreakdownは判定時刻より後の後続データは無視してtrueを返す(): void
    {
        $line = $this->createProductionLine();
        $production = $this->createProduction($line->production_line_id, '2026-04-09 10:00:00.000', 10, ProductionStatus::RUNNING);
        $this->createProduction($line->production_line_id, '2026-04-09 10:06:00.000', 11, ProductionStatus::RUNNING);
        $repository = new ProductionRepository();

        $result = $repository->judgeBreakdown($production, Carbon::parse('2026-04-09 10:05:00.000'));

        $this->assertTrue($result);
    }

    private function createPayloadData(int $lineId): PayloadData
    {
        $payloadData = new PayloadData(
            lineId: $lineId,
            defectiveCounts: [$lineId => 2],
            start: '2026-04-09 08:00:00.000',
            countSwitch: false,
            cycleTimeMs: 60000,
            overTimeMs: 120000,
            plannedOutages: [],
            changeovers: [],
            indicator: true,
        );

        $payloadData->at = '2026-04-09 08:01:00.000';
        $payloadData->count = 10;
        $payloadData->workingTime = 60000;
        $payloadData->loadingTime = 50000;
        $payloadData->operatingTime = 40000;
        $payloadData->netTime = 30000;
        $payloadData->breakdowns = [
            ['from' => '2026-04-09 08:00:30.000', 'to' => '2026-04-09 08:00:40.000'],
        ];
        $payloadData->autoResumeCount = 3;

        return $payloadData;
    }

    private function createProductionLine(): ProductionLine
    {
        $history = ProductionHistory::factory()->create();

        /** @var ProductionLine */
        return ProductionLine::query()->create([
            'production_history_id' => $history->production_history_id,
            'line_id' => null,
            'parent_id' => null,
            'line_name' => 'line-test',
            'chart_color' => '#224466',
            'ip_address' => '192.168.100.10',
            'pin_number' => 1,
            'defective' => false,
            'order' => 1,
            'indicator' => true,
            'offset_count' => null,
            'count' => 0,
        ]);
    }

    private function createProduction(int $lineId, string $at, int $count, int $status): Production
    {
        /** @var Production */
        return Production::query()->create([
            'production_line_id' => $lineId,
            'at' => $at,
            'count' => $count,
            'defective_count' => 0,
            'status' => $status,
            'in_planned_outage' => false,
            'working_time' => 0,
            'loading_time' => 0,
            'operating_time' => 0,
            'net_time' => 0,
            'breakdown_count' => 0,
            'auto_resume_count' => 0,
        ]);
    }
}
