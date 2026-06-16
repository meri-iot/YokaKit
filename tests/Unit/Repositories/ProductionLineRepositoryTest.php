<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Exceptions\ProductionException;
use App\Models\Line;
use App\Models\Process;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use App\Models\RaspberryPi;
use App\Repositories\ProductionLineRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionLineRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_modelはProductionLineクラスを返す(): void
    {
        $repository = new ProductionLineRepository();

        $this->assertSame(ProductionLine::class, $repository->model());
    }

    public function test_saveは生産ラインを追加する(): void
    {
        $line = $this->createLine('line-main', false, 2, 11);
        $history = ProductionHistory::factory()->create();
        $repository = new ProductionLineRepository();

        $saved = $repository->save($line, $history->production_history_id, '192.168.0.10', true);

        $this->assertNotNull($saved);
        $this->assertSame($line->line_id, $saved?->line_id);
        $this->assertSame($history->production_history_id, $saved?->production_history_id);
        $this->assertTrue((bool) $saved?->indicator);
        $this->assertDatabaseHas('production_lines', [
            'production_line_id' => $saved?->production_line_id,
            'line_name' => 'line-main',
            'ip_address' => '192.168.0.10',
            'pin_number' => 11,
            'defective' => 0,
            'order' => 2,
        ]);
    }

    public function test_saveDefectiveLineは不良品ラインを追加する(): void
    {
        $mainLine = $this->createLine('line-parent', false, 1, 21);
        $defectiveLine = $this->createLine('line-defective', true, 3, 22);
        $history = ProductionHistory::factory()->create();
        $repository = new ProductionLineRepository();

        $parent = $repository->save($mainLine, $history->production_history_id, '192.168.0.20', false);
        $saved = $repository->saveDefectiveLine(
            $defectiveLine,
            $history->production_history_id,
            '192.168.0.20',
            (int) $parent?->production_line_id,
        );

        $this->assertNotNull($saved);
        $this->assertSame((int) $parent?->production_line_id, $saved?->parent_id);
        $this->assertTrue((bool) $saved?->defective);
        $this->assertFalse((bool) $saved?->indicator);
    }

    public function test_lastProductionLineは指定IPとピンの最新行を返す(): void
    {
        $history = ProductionHistory::factory()->create();
        $first = $this->createProductionLine($history->production_history_id, [
            'ip_address' => '10.0.0.1',
            'pin_number' => 7,
            'line_name' => 'first',
        ]);
        $second = $this->createProductionLine($history->production_history_id, [
            'ip_address' => '10.0.0.1',
            'pin_number' => 7,
            'line_name' => 'second',
        ]);
        $repository = new ProductionLineRepository();

        $latest = $repository->lastProductionLine('10.0.0.1', 7);

        $this->assertNotNull($latest);
        $this->assertSame($second->production_line_id, $latest?->production_line_id);
        $this->assertTrue($latest?->relationLoaded('productionHistory'));
        $this->assertTrue($latest?->relationLoaded('parentLine'));
        $this->assertTrue($latest?->relationLoaded('payload'));
        $this->assertNotSame($first->production_line_id, $latest?->production_line_id);
    }

    public function test_updateLineInfoはoffset_count未設定なら初回情報を設定する(): void
    {
        $history = ProductionHistory::factory()->create();
        $productionLine = $this->createProductionLine($history->production_history_id, [
            'offset_count' => null,
            'count' => 0,
        ]);
        $repository = new ProductionLineRepository();

        $offset = $repository->updateLineInfo($productionLine, 10);

        $this->assertSame(9, $offset);
        $fresh = $productionLine->fresh();
        $this->assertSame(9, $fresh?->offset_count);
        $this->assertSame(1, $fresh?->count);
    }

    public function test_updateLineInfoはcount増加時にcountのみ更新する(): void
    {
        $history = ProductionHistory::factory()->create();
        $productionLine = $this->createProductionLine($history->production_history_id, [
            'offset_count' => 4,
            'count' => 2,
        ]);
        $repository = new ProductionLineRepository();

        $offset = $repository->updateLineInfo($productionLine, 9);

        $this->assertSame(4, $offset);
        $this->assertSame(5, $productionLine->fresh()?->count);
    }

    public function test_updateLineInfoはcount矛盾時にProductionExceptionを投げる(): void
    {
        $history = ProductionHistory::factory()->create();
        $productionLine = $this->createProductionLine($history->production_history_id, [
            'offset_count' => 4,
            'count' => 5,
        ]);
        $repository = new ProductionLineRepository();

        $this->expectException(ProductionException::class);

        $repository->updateLineInfo($productionLine, 9);
    }

    private function createLine(string $name, bool $defective, int $order, int $pin): Line
    {
        $process = Process::factory()->create();
        $raspberryPi = RaspberryPi::factory()->create();

        /** @var Line */
        return Line::query()->create([
            'process_id' => $process->process_id,
            'raspberry_pi_id' => $raspberryPi->raspberry_pi_id,
            'worker_id' => null,
            'parent_id' => null,
            'line_name' => $name,
            'chart_color' => '#123456',
            'pin_number' => $pin,
            'defective' => $defective,
            'order' => $order,
        ]);
    }

    private function createProductionLine(int $historyId, array $overrides = []): ProductionLine
    {
        $defaults = [
            'production_history_id' => $historyId,
            'line_id' => null,
            'parent_id' => null,
            'line_name' => 'prod-line',
            'chart_color' => '#abcdef',
            'ip_address' => '10.0.0.1',
            'pin_number' => 1,
            'defective' => false,
            'order' => 1,
            'indicator' => false,
            'offset_count' => null,
            'count' => 0,
        ];

        /** @var ProductionLine */
        return ProductionLine::query()->create(array_merge($defaults, $overrides));
    }
}
