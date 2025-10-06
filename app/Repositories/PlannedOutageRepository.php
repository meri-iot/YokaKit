<?php

namespace App\Repositories;

use App\Models\PlannedOutage;
use App\Models\ProcessPlannedOutage;
use Illuminate\Database\Eloquent\Collection;

/**
 * 計画停止時間リポジトリ
 *
 * @extends AbstractRepository<PlannedOutage>
 */
class PlannedOutageRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string
     */
    public function model(): string
    {
        return PlannedOutage::class;
    }

    /**
     * 指定したIDを除いた計画停止時間一覧を取得する
     *
     * @param Collection<int,ProcessPlannedOutage> $processPlannedOutages 除外する計画停止時間ID
     * @return Collection<int,PlannedOutage>
     */
    public function except(Collection $processPlannedOutages): Collection
    {
        $plannedOutages = $processPlannedOutages
            ->map(fn(ProcessPlannedOutage $x) => $x->planned_outage_id)
            ->toArray();

        return $this->model
            ->whereNotIn('planned_outage_id', $plannedOutages)
            ->get();
    }
}
