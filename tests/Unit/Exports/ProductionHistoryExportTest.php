<?php

namespace Tests\Unit\Exports;

use App\Enums\ProductionStatus;
use App\Exports\ProductionHistoryExport;
use App\Models\Production;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Tests\TestCase;

class ProductionHistoryExportTest extends TestCase
{
    public function test_collectionは生産データをExcel向け行へ変換する(): void
    {
        $at = Carbon::create(2026, 4, 8, 12, 34, 56);
        $history = new ProductionHistory([
            'count_switch' => true,
            'cycle_time' => 2.0,
            'over_time' => 1.5,
        ]);

        $indicatorLine = new ProductionLine();
        $indicatorLine->setRelation('productions', collect([
            new Production([
                'at' => $at,
                'count' => 10,
                'defective_count' => 2,
                'status' => ProductionStatus::BREAKDOWN(),
                'working_time' => 12000,
                'loading_time' => 10000,
                'operating_time' => 8000,
                'net_time' => 7000,
                'breakdown_count' => 2,
                'auto_resume_count' => 1,
            ]),
        ]));
        $history->setRelation('indicatorLine', $indicatorLine);

        $export = new ProductionHistoryExport($history);
        $rows = $export->collection()->values()->all();

        $this->assertCount(1, $rows);
        $this->assertSame(Date::dateTimeToExcel($at), $rows[0]['at']);
        $this->assertSame(4, $rows[0]['plan_count']);
        $this->assertSame(12, $rows[0]['count']);
        $this->assertSame(10, $rows[0]['good_count']);
        $this->assertSame(2, $rows[0]['defective']);
        $this->assertSame(ProductionStatus::BREAKDOWN()->description, $rows[0]['status_name']);
        $this->assertSame(12, $rows[0]['working_time']);
        $this->assertSame(10, $rows[0]['loading_time']);
        $this->assertSame(8, $rows[0]['operating_time']);
        $this->assertSame(7, $rows[0]['net_time']);
        $this->assertEqualsWithDelta(83.3333333333, $rows[0]['good_rate'], 0.000001);
        $this->assertEqualsWithDelta(250.0, $rows[0]['achievement'], 0.000001);
        $this->assertEqualsWithDelta(0.4, $rows[0]['cycle_time'], 0.000001);
        $this->assertEqualsWithDelta(80.0, $rows[0]['time_operating_rate'], 0.000001);
        $this->assertEqualsWithDelta(87.5, $rows[0]['performance_operating_rate'], 0.000001);
        $this->assertEqualsWithDelta(58.3333333333, $rows[0]['overall'], 0.000001);
    }

    public function test_collectionは指標ラインがない場合に空を返す(): void
    {
        $history = new ProductionHistory([
            'count_switch' => true,
            'cycle_time' => 2.0,
            'over_time' => 1.5,
        ]);
        $history->setRelation('indicatorLine', null);

        $export = new ProductionHistoryExport($history);

        $this->assertTrue($export->collection()->isEmpty());
    }

    public function test_collectionはサイクルタイム0秒でも0除算せず計画値を0にする(): void
    {
        $history = new ProductionHistory([
            'count_switch' => false,
            'cycle_time' => 0.0,
            'over_time' => 1.0,
        ]);

        $indicatorLine = new ProductionLine();
        $indicatorLine->setRelation('productions', collect([
            new Production([
                'at' => Carbon::create(2026, 4, 8, 13, 0, 0),
                'count' => 5,
                'defective_count' => 3,
                'status' => ProductionStatus::RUNNING(),
                'working_time' => 6000,
                'loading_time' => 6000,
                'operating_time' => 6000,
                'net_time' => 6000,
                'breakdown_count' => 0,
                'auto_resume_count' => 0,
            ]),
        ]));
        $history->setRelation('indicatorLine', $indicatorLine);

        $export = new ProductionHistoryExport($history);
        $row = $export->collection()->first();

        $this->assertSame(0, $row['plan_count']);
        $this->assertSame(0.0, $row['achievement']);
        $this->assertSame(5, $row['count']);
        $this->assertSame(2, $row['good_count']);
    }

    public function test_columnFormatsとheadingsは期待する定義を返す(): void
    {
        $export = new ProductionHistoryExport(new ProductionHistory());

        $this->assertSame([
            'A' => NumberFormat::FORMAT_DATE_TIME8,
        ], $export->columnFormats());

        $this->assertSame([
            __('yokakit.date'),
            __('yokakit.plan_count'),
            __('yokakit.number_of_production'),
            __('yokakit.good_count'),
            __('yokakit.defective_count'),
            __('yokakit.good_rate') . __('yokakit.unit_rate'),
            __('yokakit.achievement_rate') . __('yokakit.unit_rate'),
            __('yokakit.status'),
            __('yokakit.working_time') . __('yokakit.unit_sec'),
            __('yokakit.loading_time') . __('yokakit.unit_sec'),
            __('yokakit.operating_time') . __('yokakit.unit_sec'),
            __('yokakit.net_time') . __('yokakit.unit_sec'),
            __('yokakit.cycle_time') . __('yokakit.unit_sec'),
            __('yokakit.time_operating_rate') . __('yokakit.unit_rate'),
            __('yokakit.performance_operating_rate') . __('yokakit.unit_rate'),
            __('yokakit.overall_equipment_effectiveness') . __('yokakit.unit_rate'),
        ], $export->headings());
    }
}
