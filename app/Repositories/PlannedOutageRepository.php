<?php

declare(strict_types=1);

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
     * 共通 CRUD を計画停止時間モデルに適用するためのバインディング。
     *
     * @return class-string<PlannedOutage>
     */
    public function model(): string
    {
        return PlannedOutage::class;
    }

    /**
     * 指定したIDを除いた計画停止時間一覧を取得する
     *
     * 工程に紐づく計画停止時間IDを除外し、未割り当ての候補一覧を返す。
     * 同じ計画停止時間IDが複数含まれていても、一意なID集合として扱う。
     *
     * @param Collection<int,ProcessPlannedOutage> $processPlannedOutages 除外する計画停止時間ID
     * @return Collection<int,PlannedOutage>
     */
    public function except(Collection $processPlannedOutages): Collection
    {
        $plannedOutages = $processPlannedOutages
            ->map(fn(ProcessPlannedOutage $x) => $x->planned_outage_id)
            ->unique()
            ->values()
            ->toArray();

        return $this->model
            ->whereNotIn('planned_outage_id', $plannedOutages)
            ->get();
    }
}
