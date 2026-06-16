<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\CycleTime;
use App\Models\Process;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * サイクルタイムリポジトリ
 *
 * @extends AbstractRepository<CycleTime>
 */
class CycleTimeRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string<CycleTime>
     */
    public function model(): string
    {
        return CycleTime::class;
    }

    /**
     * 稼働していない品番選択用のオプションを取得する
     *
     * 対象工程に紐づくサイクルタイムのうち、現在稼働中の品番を除外して
     * 品番ID => 品番名 の配列を返す。
     *
     * @param Process $process 対象の工程
     * @return array<int,string>
     */
    public function notRunningPartNumberOptions(Process $process): array
    {
        // 稼働中の品番ID
        $runningPartNumberId = $process->productionHistory?->part_number_id;

        /** @var Builder<CycleTime> $cycleTimesQuery */
        $cycleTimesQuery = $this->model
            ->with('partNumber')
            ->where('process_id', $process->process_id);

        if (!is_null($runningPartNumberId)) {
            $cycleTimesQuery->where('part_number_id', '!=', $runningPartNumberId);
        }

        /** @var Collection<int,CycleTime> */
        $cycleTimes = $cycleTimesQuery->get(['*']);

        return $cycleTimes->reduce(function (array $carry, CycleTime $cycleTime) {
            $carry[$cycleTime->part_number_id] = $cycleTime->partNumber->part_number_name;
            return $carry;
        }, []);
    }
}
