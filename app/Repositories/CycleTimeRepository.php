<?php

namespace App\Repositories;

use App\Models\CycleTime;
use App\Models\Process;
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
     * @return class-string
     */
    public function model(): string
    {
        return CycleTime::class;
    }

    /**
     * 稼働していない品番選択用のオプションを取得する
     *
     * @param Process $process 対象の工程
     * @return array<int,string>
     */
    public function notRunningPartNumberOptions(Process $process): array
    {
        // 稼働中の品番ID
        $runningPartNumberId = $process->productionHistory?->part_number_id;

        /** @var Collection<int,CycleTime> */
        $cycleTimes = $this->model
            ->with('partNumber')
            ->where('process_id', $process->process_id)
            ->whereNot('part_number_id', $runningPartNumberId)
            ->get();

        return $cycleTimes->reduce(function (array $carry, CycleTime $cycleTime) {
            $carry[$cycleTime->part_number_id] = $cycleTime->partNumber->part_number_name;
            return $carry;
        }, []);
    }
}
