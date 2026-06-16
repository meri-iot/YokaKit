<?php

declare(strict_types=1);

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
     * @return class-string<GanttChartEvent>
     */
    public function model(): string
    {
        return GanttChartEvent::class;
    }

    /**
     * ガントチャートイベントを登録する
     *
     * @param int $processId 工程ID
     * @param int $ganttChartId ガントチャートID
     * @param bool $signal 信号
     * @param Carbon $date 時刻
     * @return GanttChartEvent|null 保存済みイベント。保存に失敗した場合は null
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
     * 指定した期間の前後境界となるガントチャートイベント時刻を求める
     *
     * 各ガントチャートについて、開始時刻より前の最新イベントと
     * 終了時刻以降の最古イベントを取得する。対象イベントが存在しない場合は、
     * 呼び出し側のクエリ範囲が崩れないよう開始・終了時刻そのものを境界値として補完する。
     *
     * @param array<int,int> $ganttChartIds ガントチャートID一覧
     * @param Carbon $startDate 取得範囲の開始時刻
     * @param Carbon $endDate 取得範囲の終了時刻
     * @return array{
     *     0: array<int,array{max_date:string,gantt_chart_id:int}>,
     *     1: array<int,array{min_date:string,gantt_chart_id:int}>
     * }
     */
    public function getEvents(array $ganttChartIds, Carbon $startDate, Carbon $endDate): array
    {
        $minEvents = $this->model
            ->select(DB::raw('MAX(at) as max_date'), 'gantt_chart_id')
            ->where('at', '<', $startDate)
            ->whereIn('gantt_chart_id', $ganttChartIds)
            ->groupBy('gantt_chart_id')
            ->get()
            ->keyBy('gantt_chart_id')
            ->toArray();

        $maxEvents = $this->model
            ->select(DB::raw('MIN(at) as min_date'), 'gantt_chart_id')
            ->where('at', '>=', $endDate)
            ->whereIn('gantt_chart_id', $ganttChartIds)
            ->groupBy('gantt_chart_id')
            ->get()
            ->keyBy('gantt_chart_id')
            ->toArray();

        $startDateString = Utility::format($startDate, 'Y-m-d H:i:s.v');
        $endDateString = Utility::format($endDate, 'Y-m-d H:i:s.v');

        foreach ($ganttChartIds as $id) {
            if (!array_key_exists($id, $minEvents)) {
                $minEvents[$id] = [
                    'max_date' => $startDateString,
                    'gantt_chart_id' => $id,
                ];
            }
            if (!array_key_exists($id, $maxEvents)) {
                $maxEvents[$id] = [
                    'min_date' => $endDateString,
                    'gantt_chart_id' => $id,
                ];
            }
        }

        return [$minEvents, $maxEvents];
    }
}
