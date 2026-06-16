<?php

declare(strict_types=1);

namespace Tests\Unit\Repositories;

use App\Enums\ProductionStatus;
use App\Models\DefectiveProduction;
use App\Models\Process;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use App\Repositories\DefectiveProductionRepository;
use App\Services\Utility;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DefectiveProductionRepositoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_modelはDefectiveProductionクラスを返す(): void
    {
        $repository = new DefectiveProductionRepository();

        $this->assertSame(DefectiveProduction::class, $repository->model());
    }

    public function test_saveは不良品データを保存してモデルを返す(): void
    {
        $productionLine = $this->createProductionLine();
        $date = Carbon::create(2026, 4, 9, 12, 34, 56, 'UTC');
        $repository = new DefectiveProductionRepository();

        $stored = $repository->save($productionLine->production_line_id, 7, $date);

        $this->assertInstanceOf(DefectiveProduction::class, $stored);
        $this->assertDatabaseHas('defective_productions', [
            'production_line_id' => $productionLine->production_line_id,
            'count' => 7,
            'at' => Utility::format($date),
        ]);
    }

    public function test_saveは保存失敗時にnullを返す(): void
    {
        $productionLine = $this->createProductionLine();
        $repository = new class extends DefectiveProductionRepository
        {
            protected function storeModel(Model $model): bool
            {
                return false;
            }
        };

        $stored = $repository->save($productionLine->production_line_id, 3, Carbon::create(2026, 4, 9, 13, 0, 0, 'UTC'));

        $this->assertNull($stored);
        $this->assertDatabaseMissing('defective_productions', [
            'production_line_id' => $productionLine->production_line_id,
            'count' => 3,
        ]);
    }

    private function createProductionLine(): ProductionLine
    {
        $process = Process::factory()->create();
        $productionHistory = ProductionHistory::factory()->create([
            'process_id' => $process->process_id,
            'process_name' => $process->process_name,
            'plan_color' => $process->plan_color,
            'part_number_name' => 'PN-DEFECTIVE',
            'count_switch' => false,
            'status' => ProductionStatus::RUNNING(),
        ]);

        return ProductionLine::query()->create([
            'production_history_id' => $productionHistory->production_history_id,
            'line_name' => 'defective-line',
            'chart_color' => '#123456',
            'ip_address' => '192.168.10.10',
            'pin_number' => 10,
            'defective' => true,
            'indicator' => false,
            'count' => 0,
            'order' => 1,
            'offset_millisecond' => 0,
        ]);
    }
}
