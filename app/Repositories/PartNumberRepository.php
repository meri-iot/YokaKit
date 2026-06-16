<?php

declare(strict_types=1);

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
     * 共通 CRUD を品番モデルに適用するためのバインディング。
     *
     * @return class-string<PartNumber>
     */
    public function model(): string
    {
        return PartNumber::class;
    }

    /**
     * 指定したIDを除いた品番一覧を取得する
     *
     * サイクルタイムに紐づく品番IDを除外し、選択肢表示に必要な列だけ返す。
     * 同じ品番IDが複数回含まれていても、除外条件は一意なID集合として扱う。
     *
     * @param Collection<int,CycleTime> $cycleTimes 除外するサイクルタイム
     * @return Collection<int,PartNumber>
     */
    public function except(Collection $cycleTimes): Collection
    {
        $partNumberIds = $cycleTimes
            ->map(fn(CycleTime $x) => $x->part_number_id)
            ->unique()
            ->values()
            ->toArray();

        return $this->model
            ->whereNotIn('part_number_id', $partNumberIds)
            ->get(['part_number_id', 'part_number_name']);
    }
}
