<?php

declare(strict_types=1);

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
        $indicatorLine = $this->history->indicatorLine;
        if (is_null($indicatorLine)) {
            return collect();
        }

        return $indicatorLine->productions
            ->map(fn(Production $production) => $this->buildRow($production));
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
     * 生産データ1件をエクスポート行へ変換する
     *
     * Excel では日時をそのまま出すと表示形式が崩れるため、日時列はシリアル値へ変換する。
     * また、実績が不完全でもエクスポートできるよう、0除算になり得る計算は全てガードする。
     *
     * @param Production $production 生産データ
     * @return array<string,float|int|string>
     */
    private function buildRow(Production $production): array
    {
        $total = $this->totalCount($production);
        $good = $total - $production->defective_count;
        $goodRate = $total === 0 ? 0.0 : $good / $total;
        $plan = $this->planCount($production);
        $timeOperatingRate = $production->loading_time === 0 ? 0.0 : $production->operating_time / $production->loading_time;
        $performanceOperatingRate = $production->operating_time === 0 ? 0.0 : $production->net_time / $production->operating_time;

        return [
            'at' => Date::dateTimeToExcel($production->at),
            'plan_count' => $plan,
            'count' => $total,
            'good_count' => $good,
            'defective' => $production->defective_count,
            'good_rate' => $goodRate * 100.0,
            'achievement' => $plan === 0 ? 0.0 : $good * 100 / $plan,
            'status_name' => $production->status->description,
            'working_time' => $production->working_time / 1000,
            'loading_time' => $production->loading_time / 1000,
            'operating_time' => $production->operating_time / 1000,
            'net_time' => $production->net_time / 1000,
            'cycle_time' => $this->cycleTime($production),
            'time_operating_rate' => $timeOperatingRate * 100.0,
            'performance_operating_rate' => $performanceOperatingRate * 100.0,
            'overall' => $goodRate * $timeOperatingRate * $performanceOperatingRate * 100.0,
        ];
    }

    /**
     * エクスポートで使用する合計生産数を取得する
     *
     * @param Production $production 生産データ
     * @return int 合計生産数
     */
    private function totalCount(Production $production): int
    {
        return $this->history->count_switch
            ? $production->count + $production->defective_count
            : $production->count;
    }

    /**
     * 計画生産数を取得する
     *
     * @param Production $production 生産データ
     * @return int 計画生産数
     */
    private function planCount(Production $production): int
    {
        $cycleTimeMs = $this->history->cycleTimeMs();
        if ($cycleTimeMs <= 0) {
            return 0;
        }

        return (int) floor($production->operating_time / $cycleTimeMs);
    }

    /**
     * サイクルタイムを取得する
     *
     * @return double サイクルタイム[s]
     */
    private function cycleTime(Production $production): float
    {
        $total = $this->totalCount($production);
        $productionCount = $total
            - $production->auto_resume_count
            - $production->breakdown_count
            + ($production->status == ProductionStatus::BREAKDOWN() ? 1 : 0);

        if ($productionCount <= 0) {
            return 0.0;
        }

        return max(0.0, ($production->net_time - $this->history->overTimeMs() * $production->breakdown_count) / ($productionCount * 1000));
    }
}
