<?php

namespace App\Http\Controllers;

use App\Exceptions\NoIndicatorException;
use App\Exports\ProductionHistoryExport;
use App\Http\Requests\StoreProductionHistoryRequest;
use App\Models\Process;
use App\Models\ProductionHistory;
use App\Services\ProductionHistoryService;
use App\Services\Utility;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * 生産履歴コントローラー
 */
class ProductionHistoryController extends AbstractController
{
    /**
     * コンストラクタ
     *
     * @param ProductionHistoryService $service 生産履歴サービス
     */
    public function __construct(private readonly ProductionHistoryService $service)
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
        return __('yokakit.production_history');
    }

    /**
     * Display a listing of the resource.
     *
     * @param Process $process 工程
     * @param Request $request
     * @return View
     */
    public function index(Process $process, Request $request): View
    {
        $partNumberName = $request->query('partNumberName');
        $startDate = $request->query('startDate');
        $endDate = $request->query('endDate');
        $histories = $this->service->histories($process->process_id, $partNumberName, $startDate, $endDate);
        $partNumbers = $this->service->productedPartNumberOptions($process);
        return view('process.production.index', ['process' => $process, 'histories' => $histories, 'partNumbers' => $partNumbers]);
    }

    /**
     * Display the specified resource.
     *
     * @param Process $process 工程
     * @param  \App\Models\ProductionHistory $history
     * @return View
     */
    public function show(Process $process, ProductionHistory $history): View
    {
        $lines = $this->service->productionLines($history->production_history_id);
        return view('process.production.show', ['process' => $process, 'history' => $history, 'lines' => $lines]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @param Process $process 工程
     * @return View
     */
    public function create(Process $process): View
    {
        $partNumbers = $this->service->partNumberOptions($process);
        return view('process.production.create', ['process' => $process, 'partNumbers' => $partNumbers]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreProductionHistoryRequest $request リクエスト
     * @param Process $process 工程
     * @return RedirectResponse
     */
    public function store(StoreProductionHistoryRequest $request, Process $process): RedirectResponse
    {
        $route = redirect()->route('process.show', ['process' => $process]);
        try {
            $result = $this->service->switchPartNumberFromForm($request, $process);
            if ($result) {
                $route->with('toast_success', __('yokakit.success_toast2', ['action' => __('yokakit.switch_part_number')]));
            } else {
                $route->with('toast_danger', __('yokakit.failed_toast2', ['action' => __('yokakit.switch_part_number')]));
            }
        } catch (NoIndicatorException $th) {
            Log::error($th->getMessage(), $th->getTrace());
            $route->with('toast_danger', __(
                'yokakit.not_exists_toast',
                [
                    'target' => __('yokakit.indicator'),
                ]
            ));
        }
        return $route;
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Process $process 工程
     * @param Request $request 削除リクエスト
     */
    public function destroy(Process $process, Request $request)
    {
        $this->authorizeAdmin();
        [$start, $end] = array_map('trim', explode('~', $request->input('date-range')));
        $deleted = $this->service->destroyHistories($request->input('checkbox'));
        return $this->redirectWithDestroy($deleted != 0, 'production.index', [
            'process' => $process,
            'partNumberName' => $request->input('part_number_name'),
            'startDate' => $start,
            'endDate' => $end,
        ]);
    }

    /**
     * 生産を停止する
     *
     * @param Process $process
     * @return RedirectResponse
     */
    public function stop(Process $process): RedirectResponse
    {
        $route = redirect()->route('process.show', ['process' => $process]);
        $action = __('yokakit.stop');
        try {
            $this->service->stop($process, true);
            $route->with('toast_success', __('yokakit.success_toast', ['target' => $this->name(), 'action' => $action]));
        } catch (Exception $th) {
            Log::error($th->getMessage(), $th->getTrace());
            $route->with('toast_danger', __('yokakit.failed_toast', ['target' => $this->name(), 'action' => $action]));
        }
        return $route;
    }

    /**
     * 段取り替えを開始する
     *
     * @param Process $process
     * @return RedirectResponse
     */
    public function startChangeover(Process $process): RedirectResponse
    {
        $route = redirect()->route('process.show', ['process' => $process]);
        try {
            $result = $this->service->changeover($process);
            if ($result) {
                $route->with('toast_success', __('yokakit.success_toast2', ['action' => __('yokakit.changeover')]));
            } else {
                $route->with('toast_danger', __('yokakit.failed_toast2', ['action' => __('yokakit.changeover')]));
            }
        } catch (Exception $th) {
            Log::error($th->getMessage(), $th->getTrace());
            $route->with('toast_danger', __('yokakit.failed_toast2', ['action' => __('yokakit.changeover')]));
        }
        return $route;
    }

    /**
     * 段取り替えを終了して生産を開始する
     *
     * @param Process $process
     * @return RedirectResponse
     */
    public function stopChangeover(Process $process): RedirectResponse
    {
        $route = redirect()->route('process.show', ['process' => $process]);
        try {
            $result = $this->service->changeover($process);
            if ($result) {
                $route->with('toast_success', __('yokakit.success_toast2', ['action' => __('yokakit.start_production')]));
            } else {
                $route->with('toast_danger', __('yokakit.failed_toast2', ['action' => __('yokakit.start_production')]));
            }
        } catch (Exception $th) {
            Log::error($th->getMessage(), $th->getTrace());
            $route->with('toast_danger', __('yokakit.failed_toast2', ['action' => __('yokakit.start_production')]));
        }
        return $route;
    }

    /**
     * 生産履歴のエクセルファイルをダウンロードする
     *
     * @param Process $process
     * @param ProductionHistory $history
     * @return BinaryFileResponse
     */
    public function download(Process $process, ProductionHistory $history): BinaryFileResponse
    {
        $filename = "{$process->process_name}-{$history->part_number_name}-{$history->production_history_id}";
        return Excel::download(new ProductionHistoryExport($history), Utility::sanitizeFileName($filename) . '.xlsx');
    }
}
