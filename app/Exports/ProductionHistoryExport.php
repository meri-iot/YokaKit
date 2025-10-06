<?php

namespace App\Exports;

use App\Enums\ProductionStatus;
use App\Models\Production;
use App\Models\ProductionHistory;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;

/**
 * 生産履歴のエクセルファイルエクスポート用クラス
 */
class ProductionHistoryExport implements FromCollection, WithStrictNullComparison, WithHeadings, WithColumnFormatting
{
    /**
     * 生産履歴のエクセルファイルエクスポート用クラスのインスタンスを生成する
     *
     * @param ProductionHistory $history エクスポート対象の履歴
     */
    public function __construct(private readonly ProductionHistory $history) {}

    /**
     * エクセルファイルに出力するデータを取得する
     *
     * @return Collection
     */
    public function collection(): Collection
    {
        return $this->history
            ->indicatorLine
            ->productions
            ->map(function (Production $p) {
                $total =  $this->history->count_switch ? $p->count + $p->defective_count : $p->count;
                $good = $total - $p->defective_count;
                $goodRate = $total == 0 ? 0 : $good / $total;
                $plan = floor($p->operating_time / ($this->history->cycle_time * 1000));
                $timeOperatingRate = $p->loading_time == 0 ? 0 : $p->operating_time / $p->loading_time;
                $performanceOperatingRate =  $p->operating_time == 0 ? 0 : $p->net_time / $p->operating_time;
                return [
                    'at' => Date::dateTimeToExcel($p->at),
                    'plan_count' => $plan,
                    'count' => $total,
                    'good_count' => $good,
                    'defective' => $p->defective_count,
                    'good_rate' => $goodRate * 100.0,
                    'achievement' => $plan == 0 ? 0 : $good * 100 / $plan,
                    'status_name' => $p->status->description,
                    'working_time' => $p->working_time / 1000,
                    'loading_time' => $p->loading_time / 1000,
                    'operating_time' => $p->operating_time / 1000,
                    'net_time' => $p->net_time / 1000,
                    'cycle_time' => $this->cycleTime($p),
                    'time_operating_rate' => $timeOperatingRate * 100.0,
                    'performance_operating_rate' => $performanceOperatingRate * 100.0,
                    'overall' => $goodRate * $timeOperatingRate * $performanceOperatingRate * 100.0,
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
        ];
    }

    /**
     * サイクルタイムを取得する
     *
     * @return double サイクルタイム[s]
     */
    private function cycleTime(Production $p)
    {
        $total =  $this->history->count_switch ? $p->count + $p->defective_count : $p->count;
        $production = $total - $p->auto_resume_count - $p->breakdown_count + ($p->status == ProductionStatus::BREAKDOWN() ? 1 : 0);
        if ($production <= 0) {
            return 0;
        } else {
            return max(0, ($p->net_time - $this->history->overTimeMs() * $p->breakdown_count) / ($production * 1000));
        }
    }
}
