<?php

namespace App\Repositories;

use App\Models\PlannedOutage;
use App\Models\ProductionPlannedOutage;
use App\Services\Utility;
use Illuminate\Database\Eloquent\Collection;

/**
 * 生産時の計画停止時間リポジトリ
 *
 * @extends AbstractRepository<ProductionPlannedOutage>
 */
class ProductionPlannedOutageRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string
     */
    public function model(): string
    {
        return ProductionPlannedOutage::class;
    }

    /**
     * 生産時の計画停止時間を登録する。
     *
     * @param PlannedOutage $plannedOutage 計画停止時間
     * @param integer $productionHistoryId 生産履歴ID
     * @return boolean 成否
     */
    public function save(PlannedOutage $plannedOutage, int $productionHistoryId): bool
    {
        $pbt = new ProductionPlannedOutage([
            'planned_outage_name' => $plannedOutage->planned_outage_name,
            'start_time' => $plannedOutage->start_time,
            'end_time' => $plannedOutage->end_time,
            'production_history_id' => $productionHistoryId,
        ]);
        return $this->storeModel($pbt);
    }

    /**
     * 計画停止時間の開始と終了までの区間の配列を取得する。
     *
     * @param integer $historyId 生産履歴ID
     * @return array<int, array{startTime: string, endTime: string}> 計画停止時間の開始と終了までの区間の配列
     */
    public function getStartEndAsArray(int $historyId): array
    {
        /** @var Collection<int,ProductionPlannedOutage> */
        $productionPlannedOutages = $this->get(
            ['production_history_id' => $historyId],
            column: ['start_time', 'end_time']
        );

        // 計画停止時間の開始と終了を取得
        $startEnd = $productionPlannedOutages
            ->map(fn(ProductionPlannedOutage $x) => [
                'startTime' => Utility::format($x->start_time, 'H:i:s'),
                'endTime' => Utility::format($x->end_time, 'H:i:s'),
            ]);

        // 日付をまたぐ計画停止時間の数をカウント
        $stackCount = $startEnd
            ->filter(fn($x) => $x['endTime'] < $x['startTime'])
            ->count();

        // 開始時間と終了時間を並び替え
        $sorted = $startEnd
            ->map(fn($x) => [
                ['datetime' => $x['startTime'], 'isStart' => true],
                ['datetime' => $x['endTime'], 'isStart' => false],
            ])
            ->flatten(1)
            ->sortBy([
                ['datetime', 'asc'],
                ['isStart', 'desc'],
            ]);

        // 計画停止時間を合成
        $start = '00:00:00';
        $mergedPlannedOutages = [];
        foreach ($sorted as $s) {
            if ($s['isStart']) {
                if ($stackCount === 0) {
                    $start = $s['datetime'];
                }
                $stackCount++;
            } else {
                $stackCount--;
                if ($stackCount === 0) {
                    array_push($mergedPlannedOutages, [
                        'startTime' => $start,
                        'endTime' => $s['datetime'],
                    ]);
                }
            }
        }
        if ($stackCount !== 0) {
            array_push($mergedPlannedOutages, [
                'startTime' => $start,
                'endTime' => '00:00:00',
            ]);
        }
        return $mergedPlannedOutages;
    }
}
