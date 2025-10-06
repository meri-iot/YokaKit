<?php

namespace App\Http\Controllers;

use App\Http\Requests\SortLineRequest;
use App\Http\Requests\StoreLineRequest;
use App\Http\Requests\UpdateLineRequest;
use App\Models\Line;
use App\Models\Process;
use App\Services\LineService;
use App\Services\Utility;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

/**
 * ラインコントローラー
 */
class LineController extends AbstractController
{
    /**
     * コンストラクタ
     *
     * @param LineService $service ラインサービス
     */
    public function __construct(private readonly LineService $service)
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
        return __('yokakit.line');
    }

    // /**
    //  * Display a listing of the resource.
    //  *
    //  * @param Process $process 工程
    //  * @return Response
    //  */
    // public function index(Process $process)
    // {
    //     //
    // }

    /**
     * Show the form for creating a new resource.
     *
     * @param Process $process 工程
     * @return View
     */
    public function create(Process $process): View
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $pinOptions = Utility::pinNumberOptions();
        $raspberryPiOptions = $this->service->raspberryPiOptions();
        $workerOptions = $this->service->workerOptions();
        $nonDefectiveLines = $this->service->nonDefectiveLineOptions($process);
        return view('process.line.create', [
            'process' => $process,
            'line' => null,
            'nonDefectiveLines' => $nonDefectiveLines,
            'pinOptions' => $pinOptions,
            'raspberryPiOptions' => $raspberryPiOptions,
            'workerOptions' => $workerOptions,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param StoreLineRequest $request リクエスト
     * @param Process $process 工程
     * @return RedirectResponse
     */
    public function store(StoreLineRequest $request, Process $process): RedirectResponse
    {
        $this->throwExceptionIfRunning($process);
        $result = $this->service->store($request);
        return $this->redirectWithStore($result, 'process.show', ['process' => $process, 'tab' => 'line']);
    }

    // /**
    //  * Display the specified resource.
    //  *
    //  * @param Process $process 工程
    //  * @param Line $line
    //  * @return Response
    //  */
    // public function show(Process $process, Line $line)
    // {
    //     //
    // }

    /**
     * Show the form for editing the specified resource.
     *
     * @param Process $process 工程
     * @param Line $line
     * @return View
     */
    public function edit(Process $process, Line $line): View
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $pinOptions = Utility::pinNumberOptions();
        $raspberryPiOptions = $this->service->raspberryPiOptions();
        $workerOptions = $this->service->workerOptions();
        $nonDefectiveLines = $this->service->nonDefectiveLineOptions($process);
        return view('process.line.edit', [
            'process' => $process,
            'line' => $line,
            'nonDefectiveLines' => $nonDefectiveLines,
            'pinOptions' => $pinOptions,
            'raspberryPiOptions' => $raspberryPiOptions,
            'workerOptions' => $workerOptions,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param UpdateLineRequest $request リクエスト
     * @param Process $process 工程
     * @param Line $line
     * @return RedirectResponse
     */
    public function update(UpdateLineRequest $request, Process $process, Line $line): RedirectResponse
    {
        $this->throwExceptionIfRunning($process);
        $result = $this->service->update($request, $line);
        return $this->redirectWithUpdate($result, 'process.show', ['process' => $process, 'tab' => 'line']);
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param Process $process 工程
     * @param Line $line
     * @return RedirectResponse
     */
    public function destroy(Process $process, Line $line): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $result = $this->service->destroy($line);
        return $this->redirectWithDestroy($result, 'process.show', ['process' => $process, 'tab' => 'line']);
    }

    /**
     * ラインの並べ替えフォーム画面を表示する。
     *
     * @param Process $process
     * @return View
     */
    public function sorting(Process $process): View
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        return view('process.line.sorting', ['process' => $process]);
    }

    /**
     * ラインの並べ替えを行う。
     *
     * @param SortLineRequest $request
     * @param Process $process
     * @return RedirectResponse
     */
    public function sort(SortLineRequest $request, Process $process): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $route = redirect()->route('process.show', ['process' => $process, 'tab' => 'line']);
        try {
            $this->service->sort($request, $process);
            $route->with('toast_success', __('yokakit.success_toast2', ['action' => __('yokakit.sort')]));
        } catch (ModelNotFoundException $e) {
            Log::error($e->getMessage(), $e->getTrace());
            $route->with('toast_danger', __('yokakit.failed_toast2', ['action' => __('yokakit.sort')]));
        }
        return $route;
    }
}
