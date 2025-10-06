<?php

namespace App\Repositories;

use App\Models\Process;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * 工程リポジトリ
 *
 * @extends AbstractRepository<Process>
 */
class ProcessRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string
     */
    public function model(): string
    {
        return Process::class;
    }

    /**
     * 指定した工程の生産を開始する
     *
     * @param Process $process 工程
     * @param integer $productionHistoryId 工程履歴ID
     * @return boolean 成否
     */
    public function start(Process $process, int $productionHistoryId): bool
    {
        return $this->updateModel($process, ['production_history_id' => $productionHistoryId]);
    }

    /**
     * 指定した工程の生産を停止する
     *
     * @param Process $process 工程
     * @return boolean 成否
     */
    public function stop(Process $process): bool
    {
        return $this->updateModel($process, ['production_history_id' => null]);
    }

    /**
     * 全てのガントチャートイベントを取得する
     *
     * @param array $minEvents ガントチャート毎の最小時刻
     * @param array $maxEvents ガントチャート毎の最大時刻
     * @return Collection<Process>
     */
    public function ganttChartEvents(array $minEvents, array $maxEvents, Carbon $start, Carbon $end): Collection
    {
        return $this->model
            ->with(['ganttCharts.ganttChartEvents' => $this->buildGanttChartEventsQuery($minEvents, $maxEvents, $start, $end)])
            ->get();
    }

    /**
     * ガントチャートイベントを取得する
     *
     * @param integer $processId 工程ID
     * @param array $minEvents ガントチャート毎の最小時刻
     * @param array $maxEvents ガントチャート毎の最大時刻
     * @return Process
     */
    public function ganttChartEvent(int $processId, array $minEvents, array $maxEvents, Carbon $start, Carbon $end): Process
    {
        return $this->first(
            ['process_id' => $processId],
            ['ganttCharts.ganttChartEvents' => $this->buildGanttChartEventsQuery($minEvents, $maxEvents, $start, $end)]
        );
    }

    protected function buildGanttChartEventsQuery(array $minEvents, array $maxEvents, Carbon $start, Carbon $end)
    {
        return function ($query) use ($minEvents, $maxEvents, $start, $end) {
            $keys = array_unique(array_merge(array_keys($minEvents), array_keys($maxEvents)));
            $query->where(function ($q) use ($keys, $minEvents, $maxEvents, $start, $end) {
                foreach ($keys as $key) {
                    $q->orWhere(function ($sub) use ($key, $minEvents, $maxEvents, $start, $end) {
                        $sub->where('gantt_chart_id', $key);
                        if (isset($minEvents[$key])) {
                            $sub->where('at', '>=', $minEvents[$key]['max_date']);
                        }
                        if (isset($maxEvents[$key])) {
                            $sub->where('at', '<=', $maxEvents[$key]['min_date']);
                        }
                    });
                }
            });
        };
    }
}
