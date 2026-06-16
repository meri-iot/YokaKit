<?php

namespace Tests\Unit\Exports;

use App\Exports\GanttChartExport;
use App\Models\GanttChart;
use App\Models\GanttChartEvent;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Tests\TestCase;

class GanttChartExportTest extends TestCase
{
    public function test_collectionは日時をexcelシリアル値で出力しsignalを0_1に変換する()
    {
        $at1 = Carbon::create(2026, 4, 8, 10, 0, 0);
        $at2 = Carbon::create(2026, 4, 8, 10, 1, 0);

        $chart = new GanttChart([
            'chart_name' => 'ExportTarget',
        ]);
        $chart->setRelation('ganttChartEvents', collect([
            new GanttChartEvent(['at' => $at1, 'signal' => true]),
            new GanttChartEvent(['at' => $at2, 'signal' => false]),
        ]));

        $export = new GanttChartExport($chart);
        $rows = $export->collection()->values()->all();

        $this->assertSame(Date::dateTimeToExcel($at1), $rows[0]['at']);
        $this->assertSame(1, $rows[0]['signal']);
        $this->assertSame(Date::dateTimeToExcel($at2), $rows[1]['at']);
        $this->assertSame(0, $rows[1]['signal']);
    }

    public function test_columnFormatsはA列を日時フォーマットにする()
    {
        $chart = new GanttChart(['chart_name' => 'Any']);
        $export = new GanttChartExport($chart);

        $this->assertSame([
            'A' => NumberFormat::FORMAT_DATE_TIME8,
        ], $export->columnFormats());
    }

    public function test_headingsは日付と信号を返す()
    {
        $chart = new GanttChart(['chart_name' => 'Any']);
        $export = new GanttChartExport($chart);

        $this->assertSame([
            __('yokakit.date'),
            __('yokakit.signal'),
        ], $export->headings());
    }

    public function test_titleは禁止文字を置換して31文字以内へ切り詰める()
    {
        $chart = new GanttChart([
            'chart_name' => '0123456789:/?*[]ABCDEF0123456789XYZ',
        ]);
        $export = new GanttChartExport($chart);

        $title = $export->title();
        $this->assertLessThanOrEqual(31, mb_strlen($title));
        $this->assertDoesNotMatchRegularExpression('/[\[\]\:\*\?\/\\\\]/', $title);
    }

    public function test_titleは空文字ならデフォルト名称を返す()
    {
        $chart = new GanttChart([
            'chart_name' => '',
        ]);
        $export = new GanttChartExport($chart);

        $this->assertSame(__('yokakit.gantt_chart'), $export->title());
    }
}
