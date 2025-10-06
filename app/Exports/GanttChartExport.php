<?php

namespace App\Exports;

use App\Models\GanttChart;
use App\Models\GanttChartEvent;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * ガントチャートのエクセルファイルエクスポート用クラス
 */
class GanttChartExport implements FromCollection, WithStrictNullComparison, WithHeadings, WithColumnFormatting, WithTitle
{
    /**
     * ガントチャートのエクセルファイルエクスポート用クラスのインスタンスを生成する
     *
     * @param GanttChart $ganttChart エクスポート対象のガントチャート
     */
    public function __construct(private readonly GanttChart $ganttChart) {}

    /**
     * エクセルファイルに出力するデータを取得する
     *
     * @return Collection
     */
    public function collection(): Collection
    {
        return $this->ganttChart->ganttChartEvents
            ->map(function (GanttChartEvent $g) {
                return [
                    'at' => $g->at,
                    'signal' => $g->signal ? 1 : 0,
                ];
            });
    }

    /**
     * 列ごとの書式を指定する
     *
     * @return array 書式
     */
    public function columnFormats(): array
    {
        return [
            'A' => NumberFormat::FORMAT_DATE_TIME8,
        ];
    }

    /**
     * エクセルファイルへのエクスポート時のヘッダーを取得する
     *
     * @return array ヘッダー文字列
     */
    public function headings(): array
    {
        return [
            __('yokakit.date'),
            __('yokakit.signal'),
        ];
    }

    /**
     * エクセルシートのタイトル
     *
     * @return string
     */
    public function title(): string
    {
        return $this->ganttChart->chart_name;
    }
}
