<?php

namespace App\Repositories;

use App\Models\GanttChart;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ガントチャートリポジトリ
 *
 * @extends AbstractRepository<GanttChart>
 */
class GanttChartRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string
     */
    public function model(): string
    {
        return GanttChart::class;
    }

    /**
     * ガントチャート並べ替えを行う
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
                        ->where('gantt_chart_id', $order)
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
}
