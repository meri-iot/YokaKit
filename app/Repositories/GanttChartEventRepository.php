<?php

namespace App\Repositories;

use App\Models\GanttChartEvent;
use App\Services\Utility;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * ガントチャートイベントリポジトリ
 *
 * @extends AbstractRepository<GanttChartEvent>
 */
class GanttChartEventRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string
     */
    public function model(): string
    {
        return GanttChartEvent::class;
    }

    /**
     * ガントチャートイベントを登録する
     *
     * @param integer $processId 工程ID
     * @param integer $ganttChartId ガントチャートID
     * @param boolean $signal 信号
     * @param Carbon $date 時刻
     * @return GanttChartEvent|null
     */
    public function storeEvent(int $processId, int $ganttChartId, bool $signal, Carbon $date): ?GanttChartEvent
    {
        $event = new GanttChartEvent([
            'process_id' => $processId,
            'gantt_chart_id' => $ganttChartId,
            'signal' => $signal,
            'at' => Utility::format($date),
        ]);
        return $this->storeModel($event) ? $event : null;
    }

    /**
     * 指定した期間から一つ前後のガントチャートイベントを含めた時刻を求める
     *
     * @param array $ganntCharts
     * @param Carbon $startDate
     * @param Carbon $endData
     * @return array
     */
    public function getEvents(array $ganntChartIds, Carbon $startDate, Carbon $endData): array
    {
        $minEvents = $this->model
            ->select(DB::raw('MAX(at) as max_date'), 'gantt_chart_id')
            ->where('at', '<', $startDate)
            ->whereIn('gantt_chart_id', $ganntChartIds)
            ->groupBy('gantt_chart_id')
            ->get()
            ->keyBy('gantt_chart_id')
            ->toArray();

        $maxEvents = $this->model
            ->select(DB::raw('MIN(at) as min_date'), 'gantt_chart_id')
            ->where('at', '>=', $endData)
            ->whereIn('gantt_chart_id', $ganntChartIds)
            ->groupBy('gantt_chart_id')
            ->get()
            ->keyBy('gantt_chart_id')
            ->toArray();

        foreach ($ganntChartIds as $id) {
            if (!array_key_exists($id, $minEvents)) {
                $minEvents[$id] = [
                    'max_date' => $startDate,
                    'gantt_chart_id' => $id,
                ];
            }
            if (!array_key_exists($id, $maxEvents)) {
                $maxEvents[$id] = [
                    'min_date' => $endData,
                    'gantt_chart_id' => $id,
                ];
            }
        }

        return [$minEvents, $maxEvents];
    }
}
