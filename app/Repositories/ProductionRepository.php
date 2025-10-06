<?php

namespace App\Repositories;

use App\Data\PayloadData;
use App\Enums\ProductionStatus;
use App\Models\Production;
use App\Services\Utility;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * 生産データリポジトリ
 *
 * @extends AbstractRepository<Production>
 */
class ProductionRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string
     */
    public function model(): string
    {
        return Production::class;
    }

    /**
     * 生産数を登録する
     *
     * @param integer $productionLineId 生産ラインID]
     * @param PayloadData $payloadData ペイロードデータ
     * @return Production|null 登録された生産データ (失敗時にはnull)
     */
    public function save(int $productionLineId, PayloadData $payloadData): ?Production
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

        return $this->storeModel($production) ? $production : null;
    }

    /**
     * チョコ停判定
     *
     * @param Production $production
     * @param Carbon $breakdownTime チョコ停時間
     * @return boolean trueの場合チョコ停発生
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
