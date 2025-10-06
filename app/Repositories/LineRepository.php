<?php

namespace App\Repositories;

use App\Models\Line;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ラインリポジトリ
 *
 * @extends AbstractRepository<Line>
 */
class LineRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string
     */
    public function model(): string
    {
        return Line::class;
    }

    /**
     * ライン並べ替えを行う
     *
     * @param integer $processId 工程ID
     * @param array<int,string> $orders 順序
     * @throws ModelNotFoundException
     */
    public function sort(int $processId, array $orders): void
    {
        DB::transaction(function () use ($processId, $orders) {
            foreach ($orders as $i => $order) {
                $result = $this->updateModel(
                    $this->model
                        ->where('line_id', $order)
                        ->where('process_id', $processId),
                    ['order' => $i]
                );
                if (!$result) {
                    throw new ModelNotFoundException();
                }
            }
        });
        Log::info('Order is updated', $orders);
    }

    /**
     * ラインの作業者を更新する
     *
     * @param integer $lineId ラインID
     * @param integer|null $workerId 作業者ID
     * @return boolean 成否
     */
    public function updateWorker(int $lineId, int|null $workerId): bool
    {
        return $this->updateModel($this->model->find($lineId), ['worker_id' => $workerId]);
    }
}
