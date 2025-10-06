<?php

namespace App\Repositories;

use App\Models\Producer;
use App\Models\ProductionLine;
use App\Models\Worker;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

/**
 * 生産者リポジトリ
 *
 * @extends AbstractRepository<Producer>
 */
class ProducerRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string
     */
    public function model(): string
    {
        return Producer::class;
    }

    /**
     * 生産者を保存する
     *
     * @param Worker $worker 作業者
     * @param integer $productionLineId 生産ラインID
     * @param Carbon $at 時刻
     * @return boolean 成否
     */
    public function save(Worker $worker, int $productionLineId, Carbon $at): bool
    {
        $p = new Producer([
            'worker_id' => $worker->worker_id,
            'production_line_id' => $productionLineId,
            'identification_number' => $worker->identification_number,
            'worker_name' => $worker->worker_name,
            'start' => $at,
        ]);
        return $this->storeModel($p);
    }

    /**
     * 生産を停止する
     *
     * @param Collection<int,ProductionLine>|Producer $obj
     * @param Carbon $date
     * @return void
     */
    public function stop(Collection|Producer $obj, Carbon $date): void
    {
        if ($obj instanceof Producer) {
            $this->updateModel($obj, ['stop' => $date]);
        } else {
            $productionLineIds = $obj
                ->map(fn(ProductionLine $x) => $x->production_line_id)
                ->toArray();
            $this->updateModel(
                $this->model->whereIn('production_line_id', $productionLineIds)->whereNull('stop'),
                ['stop' => $date]
            );
        }
    }

    /**
     * 指定した生産ラインIDの生産者を取得する
     *
     * @param integer $productionLineId 生産ラインID
     * @return Producer|null
     */
    public function findBy(int $productionLineId): ?Producer
    {
        return $this->model
            ->where('production_line_id', $productionLineId)
            ->whereNull('stop')
            ->first();
    }
}
