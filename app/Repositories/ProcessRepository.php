<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Process;
use App\Services\Utility;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * 工程リポジトリ
 *
 * 工程の CRUD 操作に加え、生産の開始・停止や
 * ガントチャートイベントの取得を担う。
 *
 * @extends AbstractRepository<Process>
 */
class ProcessRepository extends AbstractRepository
{
    /**
     * このリポジトリが扱うモデルクラスを返す
     *
     * @return class-string<Process>
     */
    public function model(): string
    {
        return Process::class;
    }

    /**
     * 指定した工程の生産を開始する
     *
     * production_history_id を設定することで「稼働中」状態に移行する。
     *
     * @param Process $process 工程
     * @param int $productionHistoryId 紐づける生産履歴ID
     * @return bool 成否
     */
    public function start(Process $process, int $productionHistoryId): bool
    {
        return $this->updateModel($process, ['production_history_id' => $productionHistoryId]);
    }

    /**
     * 指定した工程の生産を停止する
     *
     * production_history_id を null にすることで「停止中」状態に移行する。
     *
     * @param Process $process 工程
     * @return bool 成否
     */
    public function stop(Process $process): bool
    {
        return $this->updateModel($process, ['production_history_id' => null]);
    }

    /**
     * 全工程のガントチャートイベントを取得する
     *
     * 各工程に紐づくガントチャートと、その範囲内のガントチャートイベントを
     * Eager Load して返す。イベントの絞り込みは $minEvents/$maxEvents で行う。
     * $minEvents/$maxEvents には GanttChartEventRepository::getEvents() で
     * 計算済みの境界値が含まれるため、$start/$end を個別に渡す必要はない。
     *
     * @param array<int,array{max_date:string,gantt_chart_id:int}> $minEvents ガントチャートIDをキーとする「開始境界イベント」(開始時刻より前の最新イベント)
     * @param array<int,array{min_date:string,gantt_chart_id:int}> $maxEvents ガントチャートIDをキーとする「終了境界イベント」(終了時刻より後の最古イベント)
     * @return Collection<int,Process>
     */
    public function ganttChartEvents(array $minEvents, array $maxEvents): Collection
    {
        return $this->model
            ->with(['ganttCharts.ganttChartEvents' => $this->buildGanttChartEventsQuery($minEvents, $maxEvents)])
            ->get();
    }

    /**
     * 指定した工程のガントチャートイベントを取得する
     *
     * 存在しない $processId を指定した場合は ModelNotFoundException をスローする。
     *
     * @param int $processId 工程ID
     * @param array<int,array{max_date:string,gantt_chart_id:int}> $minEvents 開始境界イベント
     * @param array<int,array{min_date:string,gantt_chart_id:int}> $maxEvents 終了境界イベント
     * @return Process
     */
    public function ganttChartEvent(int $processId, array $minEvents, array $maxEvents): Process
    {
        $process = $this->first(
            ['process_id' => $processId],
            ['ganttCharts.ganttChartEvents' => $this->buildGanttChartEventsQuery($minEvents, $maxEvents)]
        );
        Utility::ensureModelExists($process);
        /** @var Process $process */
        return $process;
    }

    /**
     * ガントチャートイベントを絞り込むクエリクロージャを生成する
     *
     * 各ガントチャートについて、開始境界($minEvents の max_date 以降)かつ
     * 終了境界($maxEvents の min_date 以前)に収まるイベントのみを取得するよう
     * OR 条件を組み立てる。
     * $minEvents/$maxEvents は GanttChartEventRepository::getEvents() が
     * $start/$end を基に算出した境界値を保持しているため、ここでは不要。
     *
     * @param array<int,array{max_date:string,gantt_chart_id:int}> $minEvents 開始境界イベント
     * @param array<int,array{min_date:string,gantt_chart_id:int}> $maxEvents 終了境界イベント
    * @return Closure(Builder):void
     */
    protected function buildGanttChartEventsQuery(array $minEvents, array $maxEvents): Closure
    {
        return function ($query) use ($minEvents, $maxEvents) {
            $keys = array_unique(array_merge(array_keys($minEvents), array_keys($maxEvents)));
            $query->where(function ($q) use ($keys, $minEvents, $maxEvents) {
                foreach ($keys as $key) {
                    $q->orWhere(function ($sub) use ($key, $minEvents, $maxEvents) {
                        $sub->where('gantt_chart_id', $key);
                        if (isset($minEvents[$key])) {
                            // 開始境界以降のイベントに絞る
                            $sub->where('at', '>=', $minEvents[$key]['max_date']);
                        }
                        if (isset($maxEvents[$key])) {
                            // 終了境界以前のイベントに絞る
                            $sub->where('at', '<=', $maxEvents[$key]['min_date']);
                        }
                    });
                }
            });
        };
    }
}
