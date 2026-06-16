<?php

declare(strict_types=1);

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
     * @return class-string<GanttChart>
     */
    public function model(): string
    {
        return GanttChart::class;
    }

    /**
     * 指定した ID 一覧を行ロック付きで取得する
     *
     * @param array<int,int> $ids
     * @return \Illuminate\Database\Eloquent\Collection<int,GanttChart>
     */
    public function getWithLockByIds(array $ids): \Illuminate\Database\Eloquent\Collection
    {
        return $this->model->lockForUpdate()->findMany($ids);
    }

    /**
     * ガントチャート並べ替えを行う
     *
     * @param int $processId 工程ID
     * @param array<int,int> $orders 並び順に並んだガントチャートID一覧
     * @throws ModelNotFoundException
     */
    public function sort(int $processId, array $orders): void
    {
        DB::transaction(function () use ($processId, $orders) {
            foreach ($orders as $index => $ganttChartId) {
                $ganttChart = $this->model
                    ->where('gantt_chart_id', $ganttChartId)
                    ->where('process_id', $processId)
                    ->first();

                if (is_null($ganttChart)) {
                    throw new ModelNotFoundException();
                }

                // `order` は fillable ではないため、直接代入して保存する。
                $ganttChart->order = $index;
                $result = $ganttChart->save();
                if (!$result) {
                    throw new ModelNotFoundException();
                }
            }
        });
        Log::info('Order is updated', [
            'process_id' => $processId,
            'orders' => $orders,
        ]);
    }
}
