<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Exceptions\NoIndicatorException;
use App\Http\Requests\StoreProductionHistoryRequest;
use App\Http\Requests\UpdateLineWorkerRequest;
use App\Models\Process;
use App\Services\ProductionHistoryService;
use App\Services\SwitchService;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * 品番&作業者入れ替えコントローラー
 */
class SwitchController extends BaseController
{
    /**
     * コンストラクタ
     *
     * @param SwitchService $service
     * @param ProductionHistoryService $productionHistoryService
     */
    public function __construct(
        private readonly SwitchService $service,
        private readonly ProductionHistoryService $productionHistoryService
    ) {
        $this->middleware('auth');
    }

    /**
     * 品番切り替え・作業者入れ替え画面を表示する。
     *
     * @param  Request $request
     * @return View
     */
    public function index(Request $request): View
    {
        $processes = $this->service->processes();
        $plannedOutages = $processes->reduce(function (array $carry, Process $x) {
            $carry[$x->process_id] = $x->productionHistory?->inPlannedOutage() ?? false;
            return $carry;
        }, []);
        $workers = $this->service->workers();
        $initialId = $request->input('process');
        return view('switch.index', [
            'processes' => $processes,
            'workers' => $workers,
            'initialId' => $initialId,
            'plannedOutages' => $plannedOutages,
        ]);
    }

    /**
     * 品番切り替えを実行する。
     *
     * @param  \App\Http\Requests\StoreProductionHistoryRequest $request
     * @param Process $process 工程
     * @return RedirectResponse
     */
    public function store(StoreProductionHistoryRequest $request, Process $process): RedirectResponse
    {
        $route = redirect()->route('switch.index', ['process' => $process]);
        try {
            $result = $this->productionHistoryService->switchPartNumberFromForm($request, $process);
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
     * 生産を停止する
     *
     * @param Process $process
     * @return RedirectResponse
     */
    public function stop(Process $process): RedirectResponse
    {
        $route = redirect()->route('switch.index', ['process' => $process]);
        $action = __('yokakit.stop');
        $target = __('yokakit.production');
        $session = ['target' => $target, 'action' => $action];
        try {
            $this->productionHistoryService->stop($process, true);
            $route->with('toast_success', __('yokakit.success_toast', $session));
        } catch (Exception $th) {
            Log::error($th->getMessage(), $th->getTrace());
            $route->with('toast_danger', __('yokakit.failed_toast', $session));
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
        $route = redirect()->route('switch.index', ['process' => $process]);
        try {
            $result = $this->productionHistoryService->changeover($process);
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
        $route = redirect()->route('switch.index', ['process' => $process]);
        try {
            $result = $this->productionHistoryService->changeover($process);
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
     * 作業者の入れ替えを行う
     *
     * @param UpdateLineWorkerRequest $request
     * @param Process $process
     * @return RedirectResponse
     */
    public function changeWorker(UpdateLineWorkerRequest $request, Process $process): RedirectResponse
    {
        $route = redirect()->route('switch.index', ['process' => $process]);
        $action = __('yokakit.replace');
        try {
            $this->service->updateLineWorker($request, $process);
            $route->with('toast_success', __('yokakit.success_toast', ['target' => __('yokakit.worker'), 'action' => $action]));
        } catch (Exception $th) {
            Log::error($th->getMessage(), $th->getTrace());
            $route->with('toast_danger', __('yokakit.failed_toast', ['target' => __('yokakit.worker'), 'action' => $action]));
        }
        return $route;
    }
}
