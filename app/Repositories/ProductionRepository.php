<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\PayloadData;
use App\Enums\ProductionStatus;
use App\Models\Production;
use App\Services\Utility;
use Illuminate\Support\Carbon;

/**
 * 生産データリポジトリ
 *
 * @extends AbstractRepository<Production>
 */
class ProductionRepository extends AbstractRepository
{
    /**
     * このリポジトリが扱うモデルクラスを返す
     *
     * @return class-string<Production>
     */
    public function model(): string
    {
        return Production::class;
    }

    /**
     * 生産数を登録する
     *
     * @param int $productionLineId 生産ラインID
     * @param PayloadData $payloadData ペイロードデータ
     * @return Production 登録された生産データ
     */
    public function save(int $productionLineId, PayloadData $payloadData): Production
    {
        $production = new Production([
            'production_line_id' => $productionLineId,
            'at' => $payloadData->at,
            'count' => $payloadData->count,
            'defective_count' => $payloadData->defectiveCount(),
            'status' => $payloadData->status(),
            'in_planned_outage' => $payloadData->inPlannedOutage(),
            'working_time' => $payloadData->workingTime,
            'loading_time' => $payloadData->loadingTime,
            'operating_time' => $payloadData->operatingTime,
            'net_time' => $payloadData->netTime,
            'breakdown_count' => count($payloadData->breakdowns),
            'auto_resume_count' => $payloadData->autoResumeCount,
        ]);

        Utility::ensureOperationSucceeded($production, $this->storeModel($production));
        return $production;
    }

    /**
     * チョコ停判定
     *
     * 基準となる生産データより後に、判定時刻までの間で
     * 「生産数増加」または「稼働中以外のステータス」が発生していなければ
     * チョコ停と判定する。
     *
     * @param Production $production
     * @param Carbon $breakdownTime チョコ停時間
     * @return bool trueの場合チョコ停発生
     */
    public function judgeBreakdown(Production $production, Carbon $breakdownTime): bool
    {
        return !$this->model
            ->where('production_id', '>', $production->production_id)
            ->where('production_line_id', $production->production_line_id)
            ->where('at', '<=', Utility::format($breakdownTime))
            ->where(function ($query) use ($production) {
                $query->where('count', '>', $production->count)
                    ->orWhere('status', '<>', ProductionStatus::RUNNING());
            })
            ->exists();
    }
}
