<?php

namespace App\Services;

use App\Data\FromTo;
use App\Enums\GanttChartType;
use App\Events\GanttChartNotification;
use App\Http\Requests\SortGanttChartRequest;
use App\Http\Requests\StoreGanttChartRequest;
use App\Http\Requests\UpdateGanttChartRequest;
use App\Models\GanttChart;
use App\Models\Process;
use App\Repositories\GanttChartEventRepository;
use App\Repositories\GanttChartRepository;
use App\Repositories\ProcessRepository;
use App\Repositories\RaspberryPiRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * ガントチャートサービス
 */
class GanttChartService
{
    private readonly ProcessRepository $process;
    private readonly GanttChartRepository $ganttChart;
    private readonly GanttChartEventRepository $ganttChartEvent;
    private readonly RaspberryPiRepository $raspberryPi;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->process = App::make(ProcessRepository::class);
        $this->raspberryPi = App::make(RaspberryPiRepository::class);
        $this->ganttChartEvent = App::make(GanttChartEventRepository::class);
        $this->ganttChart = App::make(GanttChartRepository::class);
    }

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
        $ganntChartIds = $this->ganttChart->all()->map(fn(GanttChart $g) => $g->gantt_chart_id)->toArray();
        [$minEvents, $maxEvents] = $this->ganttChartEvent->getEvents($ganntChartIds, $start, $end);
        return $this->process->ganttChartEvents($minEvents, $maxEvents, $start, $end);
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
        $ganntChartIds = $this->ganttChart->get(['process_id' => $process->process_id])->map(fn(GanttChart $g) => $g->gantt_chart_id)->toArray();
        [$minEvents, $maxEvents] = $this->ganttChartEvent->getEvents($ganntChartIds, $start, $end);
        return $this->process->ganttChartEvent($process->process_id, $minEvents, $maxEvents, $start, $end);
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
        $ganntChartIds = $this->ganttChart->get(['process_id' => $process->process_id])->map(fn(GanttChart $g) => $g->gantt_chart_id)->toArray();
        [$minEvents, $maxEvents] =  $this->ganttChartEvent->getEvents($ganntChartIds, $startDate, $endDate);
        $p = $this->process->ganttChartEvent($process->process_id, $minEvents, $maxEvents, $startDate, $endDate);
        $p->range = $endDate->diffInMinutes($startDate);

        /** @var Collection<FromTo> */
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
     * Undocumented function
     *
     * @param Collection $events
     * @param Carbon $startDate
     * @param Carbon $endDate
     * @param Carbon $now
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
                $to = $now->clone();
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
    function getOverlappings(Collection $base, Collection $work): Collection
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
     * @return boolean 成否
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
     * @return boolean 成否
     */
    public function update(UpdateGanttChartRequest $request, GanttChart $ganttChart): bool
    {
        return $this->ganttChart->update($request, $ganttChart);
    }

    /**
     * ガントチャートを削除する
     *
     * @param GanttChart $ganttChart 削除対象のガントチャート
     * @return boolean 成否
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

    public function insert(int $pinNumber, string $ipAddress, bool $signal): void
    {
        // 対象のラズパイを検索
        $raspi = $this->raspberryPi->first(['ip_address' => $ipAddress]);
        if (is_null($raspi)) {
            // Log::warning('Raspberry pi not found', [$ipAddress]);
            return;
        }

        // 対象のガントチャートを検索
        $ganttChart = $this->ganttChart->first([
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'pin_number' => $pinNumber,
        ]);
        if (is_null($ganttChart)) {
            Log::warning('Gantt Chart not found', [$pinNumber]);
            return;
        }

        // ガントチャートを更新
        DB::transaction(function () use ($ganttChart, $signal) {
            $now = Utility::now();
            $processId = $ganttChart->process_id;
            $ganttChartId = $ganttChart->gantt_chart_id;
            $previous = $ganttChart->signal;
            $ganttChart->signal = $signal;
            $ganttChart->save();
            if (is_null($previous)) {
                if ($signal === $ganttChart->trigger) {
                    $event = $this->ganttChartEvent->storeEvent($processId, $ganttChartId, true, $now);
                    if (!is_null($event)) {
                        GanttChartNotification::dispatch($event);
                    }
                }
            } else if ($previous !== $signal) {
                $event = $this->ganttChartEvent->storeEvent($processId, $ganttChartId, $signal === $ganttChart->trigger, $now);
                if (!is_null($event)) {
                    GanttChartNotification::dispatch($event);
                }
            }
        });
    }
}
