<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\FromTo;
use App\Enums\GanttChartType;
use App\Events\GanttChartNotification;
use App\Http\Requests\SortGanttChartRequest;
use App\Http\Requests\StoreGanttChartRequest;
use App\Http\Requests\UpdateGanttChartRequest;
use App\Models\GanttChart;
use App\Models\Process;
use App\Models\GanttChartEvent;
use App\Repositories\GanttChartEventRepository;
use App\Repositories\GanttChartRepository;
use App\Repositories\ProcessRepository;
use App\Repositories\RaspberryPiRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ガントチャートサービス
 *
 * ガントチャートの CRUD・イベント取得・MQTT 信号受信処理を束ねる。
 */
class GanttChartService
{
    /**
     * コンストラクタ
     */
    public function __construct(
        private readonly ProcessRepository $process,
        private readonly GanttChartRepository $ganttChart,
        private readonly GanttChartEventRepository $ganttChartEvent,
        private readonly RaspberryPiRepository $raspberryPi,
    ) {}

    /**
     * すべての工程のガントチャートイベントを取得する
     *
     * @return Collection<int,Process>
     */
    public function getEvents(): Collection
    {
        $now = Utility::now();
        $start = $now->clone()->startOfDay();
        $end = $now->addDay()->startOfDay();
        $ganttChartIds = $this->ganttChart->all()->map(fn(GanttChart $g) => $g->gantt_chart_id)->toArray();
        [$minEvents, $maxEvents] = $this->ganttChartEvent->getEvents($ganttChartIds, $start, $end);
        return $this->process->ganttChartEvents($minEvents, $maxEvents);
    }

    /**
     * 指定した工程のガントチャートイベントを取得する
     *
     * @param Process $process
     * @return Process
     */
    public function getEvent(Process $process): Process
    {
        $now = Utility::now();
        $start = $now->clone()->startOfDay();
        $end = $now->addDay()->startOfDay();
        $ganttChartIds = $this->ganttChart->get(['process_id' => $process->process_id])->map(fn(GanttChart $g) => $g->gantt_chart_id)->toArray();
        [$minEvents, $maxEvents] = $this->ganttChartEvent->getEvents($ganttChartIds, $start, $end);
        return $this->process->ganttChartEvent($process->process_id, $minEvents, $maxEvents);
    }

    /**
     * 指定した工程のガントチャートイベントを取得する
     *
     * @param Process $process
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @return Process
     */
    public function getEventWithRange(Process $process, Carbon $startDate, Carbon $endDate): Process
    {
        $ganttChartIds = $this->ganttChart->get(['process_id' => $process->process_id])->map(fn(GanttChart $g) => $g->gantt_chart_id)->toArray();
        [$minEvents, $maxEvents] = $this->ganttChartEvent->getEvents($ganttChartIds, $startDate, $endDate);
        $p = $this->process->ganttChartEvent($process->process_id, $minEvents, $maxEvents);
        $p->range = $endDate->diffInMinutes($startDate);

        $base = collect();
        $now = Utility::now();
        $baseSpan = 0;
        foreach ($p->ganttCharts->sortBy('chart_type') as $g) {
            if ($g->chart_type == GanttChartType::BASE()) {
                $base = $this->getSections($g->ganttChartEvents, $startDate, $endDate, $now);
                $baseSpan = $base->sum(fn(FromTo $b) => $b->span());
                $g->base_span = $baseSpan;
                $g->work_span = 0;
                $g->overlap_span = 0;
            } else if ($g->chart_type == GanttChartType::WORK()) {
                $work = $this->getSections($g->ganttChartEvents, $startDate, $endDate, $now);
                $workSpan = $work->sum(fn(FromTo $w) => $w->span());
                $overlaps = $this->getOverlappings($base, $work);
                $overlapSpan = $overlaps->sum(fn(FromTo $o) => $o->span());
                $g->base_span = $baseSpan;
                $g->work_span = $workSpan;
                $g->overlap_span = $overlapSpan;
            } else {
                $g->base_span = 0;
                $g->work_span = 0;
                $g->overlap_span = 0;
            }
        }
        return $p;
    }

    /**
     * イベント列から ON 状態の時間区間を取得する
     *
     * 各イベントについて signal が true の区間を FromTo として収集する。
     * 区間の端は $startDate/$endDate でクランプされる。
     * 末尾のイベントが ON の場合は $now を終端として扱う。
     *
     * @param Collection $events ガントチャートイベント一覧
     * @param Carbon $startDate 取得範囲の開始時刻
     * @param Carbon $endDate 取得範囲の終了時刻
     * @param Carbon $now 現在時刻（末尾 ON イベントの終端として使用）
     * @return Collection<FromTo>
     */
    private function getSections(Collection $events, Carbon $startDate, Carbon $endDate, Carbon $now): Collection
    {
        $collection = collect();
        $count = $events->count();
        for ($i = 0; $i < $count; $i++) {
            $event = $events[$i];
            if (!$event->signal) {
                continue;
            }
            if ($i + 1 < $count) {
                $next = $events[$i + 1];
                $from = $event->at->max($startDate)->min($endDate)->clone();
                $to = $next->at->max($startDate)->min($endDate)->clone();
            } else {
                $from = $event->at->max($startDate)->min($endDate)->clone();
                $to = $now->clone()->min($endDate);
            }
            if ($from <= $to) {
                $collection->add(new FromTo($from, $to));
            }
        }
        return $collection;
    }

    /**
     * 指定した基準区間と稼働区間から重複している区間を取得する
     *
     * @param Collection<FromTo> $base 基準区間
     * @param Collection<FromTo> $work 稼働区間
     * @return Collection<FromTo> 重複区間
     */
    private function getOverlappings(Collection $base, Collection $work): Collection
    {
        $overlaps = collect();
        foreach ($base as $b) {
            foreach ($work as $w) {
                $start = $b->from->max($w->from);
                $end = $b->to->min($w->to);
                if ($start <= $end) {
                    $overlaps->add(new FromTo($start->clone(), $end->clone()));
                }
            }
        }
        return $overlaps;
    }

    /**
     * ラズベリーパイ選択用のオプションを取得する
     *
     * @return array<int,string> ラズベリーパイ選択用のオプション
     */
    public function raspberryPiOptions(): array
    {
        return $this->raspberryPi->options();
    }

    /**
     * ガントチャートを追加する
     *
     * @param StoreGanttChartRequest $request ガントチャート追加リクエスト
     * @param Process $process 追加対象の工程
     * @return bool 成否
     */
    public function store(StoreGanttChartRequest $request, Process $process): bool
    {
        return DB::transaction(function () use ($request, $process) {
            $result = $this->ganttChart->store($request);
            if ($result) {
                $ganttChart = $this->ganttChart->first([
                    'process_id' => $process->process_id,
                    'chart_name' => $request['chart_name'],
                ]);
                $this->ganttChartEvent->storeEvent($process->process_id, $ganttChart->gantt_chart_id, false, Utility::now());
            }
            return $result;
        });
    }

    /**
     * ガントチャートを更新する
     *
     * @param UpdateGanttChartRequest $request ガントチャート更新リクエスト
     * @param GanttChart $ganttChart 更新対象のガントチャート
     * @return bool 成否
     */
    public function update(UpdateGanttChartRequest $request, GanttChart $ganttChart): bool
    {
        return $this->ganttChart->update($request, $ganttChart);
    }

    /**
     * ガントチャートを削除する
     *
     * @param GanttChart $ganttChart 削除対象のガントチャート
     * @return bool 成否
     */
    public function destroy(GanttChart $ganttChart): bool
    {
        return $this->ganttChart->destroy($ganttChart);
    }

    /**
     * ガントチャートの並べ替えを行う
     *
     * @param SortGanttChartRequest $request ガントチャート並べ替えリクエスト
     * @param Process $process 工程
     * @throws ModelNotFoundException
     */
    public function sort(SortGanttChartRequest $request, Process $process): void
    {
        $this->ganttChart->sort($process->process_id, $request->order);
    }

    /**
     * MQTT 信号を受け取り、対応するガントチャートを更新する
     *
     * IPアドレスとピン番号でガントチャートを特定し、信号の変化に応じて
     * イベントを記録する。ラズパイやガントチャートが存在しない場合は早期リターンする。
     *
     * @param int|string $pinNumber ピン番号
     * @param string $ipAddress ラズパイの IP アドレス
     * @param bool $signal 受信した信号
     */
    public function insert(int|string $pinNumber, string $ipAddress, bool $signal): void
    {
        // 対象のラズパイを検索
        $raspi = $this->raspberryPi->first(['ip_address' => $ipAddress]);
        if (is_null($raspi)) {
            // Log::warning('Raspberry pi not found', ['ip_address' => $ipAddress]);
            return;
        }

        // 対象のガントチャートを検索
        $ganttCharts = $this->ganttChart->get([
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'pin_number' => $pinNumber,
        ]);

        if ($ganttCharts->isEmpty()) {
            Log::warning('Gantt Chart not found', [$pinNumber]);
            return;
        }

        // ガントチャートを更新
        $ganttChartIds = $ganttCharts->pluck('gantt_chart_id')->all();
        DB::transaction(function () use ($ganttChartIds, $signal) {
            /** @var array<int,GanttChartEvent> $eventsToNotify */
            $eventsToNotify = [];
            $now = Utility::now();
            // 行ロックを取得してから最新の signal を読み込む（競合状態を防止）
            $lockedCharts = $this->ganttChart->getWithLockByIds($ganttChartIds);
            foreach ($lockedCharts as $ganttChart) {
                $processId = $ganttChart->process_id;
                $ganttChartId = $ganttChart->gantt_chart_id;
                /** @var bool|null $previous */
                $previous = $ganttChart->signal;
                $newSignal = ($signal === $ganttChart->trigger);
                $ganttChart->signal = $newSignal;
                $ganttChart->save();

                $event = null;
                if (is_null($previous)) {
                    if ($newSignal === true) {
                        $event = $this->ganttChartEvent->storeEvent($processId, $ganttChartId, true, $now);
                    }
                } else if ($previous !== $newSignal) {
                    $event = $this->ganttChartEvent->storeEvent($processId, $ganttChartId, $newSignal, $now);
                }

                if (!is_null($event)) {
                    $eventsToNotify[] = $event;
                }
            }
            if ($eventsToNotify === []) {
                return;
            }

            DB::afterCommit(function () use ($eventsToNotify) {
                foreach ($eventsToNotify as $event) {
                    GanttChartNotification::dispatch($event);
                }
            });
        });
    }
}
