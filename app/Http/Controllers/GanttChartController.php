<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\GanttChartType;
use App\Exports\MultiSheetGanttChartExport;
use App\Http\Requests\SortGanttChartRequest;
use App\Http\Requests\StoreGanttChartRequest;
use App\Http\Requests\UpdateGanttChartRequest;
use App\Models\GanttChart;
use App\Models\Process;
use App\Services\GanttChartService;
use App\Services\Utility;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * ガントチャートコントローラー
 */
class GanttChartController extends AbstractController
{
    /**
     * コンストラクタ
     *
     * @param GanttChartService $service ガントチャートサービス
     */
    public function __construct(private readonly GanttChartService $service)
    {
        $this->middleware('auth');
    }

    /**
     * UI表示用名称を取得する
     *
     * @return string 名称
     */
    public function name(): string
    {
        return __('yokakit.gantt_chart');
    }

    /**
     * Display a listing of the resource.
     *
     * @param Process $process
     * @return View
     */
    public function index(Process $process): View
    {
        $process = $this->service->getEvent($process);
        return view('process.gantt-chart.index', ['process' => $process, 'start' => null]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param Process $process
     * @return View
     */
    public function create(Process $process): View
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $raspberryPiOptions = $this->service->raspberryPiOptions();
        $pinOptions = Utility::pinNumberOptions();
        $chatTypes = array_combine(GanttChartType::getValues(), array_map(fn($x) => $x->description, GanttChartType::getInstances()));
        return view('process.gantt-chart.create', [
            'process' => $process,
            'raspberryPiOptions' => $raspberryPiOptions,
            'pinOptions' => $pinOptions,
            'chatTypes' => $chatTypes,
        ]);
    }

    /**
     * 新しいガントチャートをストレージに保存する。管理者のみ使用可能。
     *
     * @param StoreGanttChartRequest  $request
     * @param Process $process
     * @return RedirectResponse
     */
    public function store(StoreGanttChartRequest $request, Process $process): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $result = $this->service->store($request, $process);
        return $this->redirectWithStore($result, 'process.show', ['process' => $process, 'tab' => 'gantt-chart']);
    }

    /**
     * ガントチャートを表示する。このメソッドは使用されません。
     *
     * @param  \App\Models\GanttChart  $ganttChart
     * @return Response
     */
    public function show(GanttChart $ganttChart): Response
    {
        return response()->noContent();
    }

    /**
     * ガントチャート編集フォーム画面を表示する。管理者のみ使用可能。
     *
     * @param Process $process
     * @param GanttChart $ganttChart
     * @return View
     */
    public function edit(Process $process, GanttChart $ganttChart): View
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $raspberryPiOptions = $this->service->raspberryPiOptions();
        $pinOptions = Utility::pinNumberOptions();
        $chatTypes = array_combine(GanttChartType::getValues(), array_map(fn($x) => $x->description, GanttChartType::getInstances()));
        return view('process.gantt-chart.edit', [
            'process' => $process,
            'ganttChart' => $ganttChart,
            'raspberryPiOptions' => $raspberryPiOptions,
            'pinOptions' => $pinOptions,
            'chatTypes' => $chatTypes,
        ]);
    }

    /**
     * ガントチャートを更新する。管理者のみ使用可能。
     *
     * @param UpdateGanttChartRequest $request
     * @param Process $process
     * @param GanttChart $ganttChart
     * @return RedirectResponse
     */
    public function update(UpdateGanttChartRequest $request, Process $process, GanttChart $ganttChart): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $result = $this->service->update($request, $ganttChart);
        return $this->redirectWithUpdate($result, 'process.show', ['process' => $process, 'tab' => 'gantt-chart']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Process $process 工程
     * @param GanttChart $ganttChart ガントチャート
     * @return RedirectResponse
     */
    public function destroy(Process $process, GanttChart $ganttChart): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $result = $this->service->destroy($ganttChart);
        return $this->redirectWithDestroy($result, 'process.show', ['process' => $process, 'tab' => 'gantt-chart']);
    }

    /**
     * ガントチャートの並べ替えフォーム画面を表示する。
     *
     * @param Process $process
     * @return View
     */
    public function sorting(Process $process): View
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        return view('process.gantt-chart.sorting', ['process' => $process]);
    }

    /**
     * ガントチャートの並べ替えを行う。
     *
     * @param SortGanttChartRequest $request
     * @param Process $process
     * @return RedirectResponse
     */
    public function sort(SortGanttChartRequest $request, Process $process): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $route = redirect()->route('process.show', ['process' => $process, 'tab' => 'gantt-chart']);
        try {
            $this->service->sort($request, $process);
            $route->with('toast_success', __('yokakit.success_toast2', ['action' => __('yokakit.sort')]));
        } catch (ModelNotFoundException $e) {
            Log::error($e->getMessage(), $e->getTrace());
            $route->with('toast_danger', __('yokakit.failed_toast2', ['action' => __('yokakit.sort')]));
        }
        return $route;
    }

    /**
     * 全てのガントチャートを表示する。
     *
     * @return View
     */
    public function all(): View
    {
        $processes = $this->service->getEvents();
        return view('gantt-chart.index', [
            'processes' => $processes,
            'start' => null,
        ]);
    }

    /**
     * ガントチャートの履歴を表示する
     *
     * @param Process $process 工程
     * @param Request $request
     * @return View
     */
    public function history(Process $process, Request $request): View
    {
        $now = Utility::now();
        $start = $now->clone()->startOfDay();
        $end = $now->clone()->addDay()->startOfDay();

        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');

        if (!is_null($startDate) && $startDate !== '' && !is_null($endDate) && $endDate !== '') {
            $start = Utility::parse($startDate, 'Y-m-d')->startOfDay();
            $end = Utility::parse($endDate, 'Y-m-d')->addDay()->startOfDay();
        }

        $startDate = Utility::format($start, 'Y-m-d');
        $process = $this->service->getEventWithRange($process, $start, $end);
        return view('process.gantt-chart.history', ['process' => $process, 'start' => $startDate]);
    }

    /**
     * ガントチャートの履歴をダウンロードする
     *
     * @param Process $process 工程
     * @param Request $request
     * @return BinaryFileResponse
     */
    public function download(Process $process, Request $request): BinaryFileResponse
    {
        $now = Utility::now();
        $start = $now->clone()->startOfDay();
        $end = $now->clone()->addDay()->startOfDay();

        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');

        if (!is_null($startDate) && $startDate !== '' && !is_null($endDate) && $endDate !== '') {
            $start = Utility::parse($startDate, 'Y-m-d')->startOfDay();
            $end = Utility::parse($endDate, 'Y-m-d')->addDay()->startOfDay();
        }

        $s = Utility::format($start, 'Ymd');
        $e = Utility::format($end, 'Ymd');
        $filename = "{$process->process_name}-{$s}-{$e}";
        return Excel::download(new MultiSheetGanttChartExport($this->service, $process, $start, $end), Utility::sanitizeFileName($filename) . '.xlsx');
    }
}
