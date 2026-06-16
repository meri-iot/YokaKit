<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\GanttChart;
use App\Models\GanttChartEvent;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Shared\Date;
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
    public function __construct(
        private readonly GanttChart $ganttChart,
        private readonly ?string $sheetTitle = null,
    ) {}

    /**
     * Excelシート名として利用できる文字列へ正規化する
     *
     * @param string $title 元のシート名
     * @return string 正規化後のシート名
     */
    public static function normalizeSheetTitle(string $title): string
    {
        $normalized = preg_replace('/[\[\]\:\*\?\/\\\\]/', '_', $title);
        $normalized = trim((string) $normalized);
        return mb_substr($normalized === '' ? __('yokakit.gantt_chart') : $normalized, 0, 31);
    }

    /**
     * エクセルファイルに出力するデータを取得する
     *
     * @return Collection
     */
    public function collection(): Collection
    {
        return $this->ganttChart->ganttChartEvents
            ->map(function (GanttChartEvent $g) {
                $at = $g->at;
                return [
                    // Excelの日付表示を確実にするため、シリアル値で出力する。
                    'at' => Date::dateTimeToExcel($at),
                    'signal' => $g->signal ? 1 : 0,
                ];
            });
    }

    /**
     * 列ごとの書式を指定する
     *
     * @return array<string,string> 書式
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
     * @return array<int,string> ヘッダー文字列
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
        return self::normalizeSheetTitle($this->sheetTitle ?? $this->ganttChart->chart_name);
    }
}
