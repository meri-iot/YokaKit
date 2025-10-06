<?php

namespace App\Repositories;

use App\Models\CycleTime;
use App\Models\PartNumber;
use Illuminate\Database\Eloquent\Collection;

/**
 * 品番リポジトリ
 *
 * @extends AbstractRepository<PartNumber>
 */
class PartNumberRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string
     */
    public function model(): string
    {
        return PartNumber::class;
    }

    /**
     * 指定したIDを除いた品番一覧を取得する
     *
     * @param Collection<int,CycleTime> $cycleTimes 除外するサイクルタイム
     * @return Collection<int,PartNumber>
     */
    public function except(Collection $cycleTimes): Collection
    {
        $partNumberIds = $cycleTimes
            ->map(fn(CycleTime $x) => $x->part_number_id)
            ->toArray();
        return $this->model
            ->whereNotIn('part_number_id', $partNumberIds)
            ->get(['part_number_id', 'part_number_name']);
    }
}
