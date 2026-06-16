<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreCycleTimeRequest;
use App\Http\Requests\UpdateCycleTimeRequest;
use App\Models\CycleTime;
use App\Models\Process;
use App\Services\CycleTimeService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * サイクルタイムコントローラー
 */
class CycleTimeController extends AbstractController
{
    /**
     * コンストラクタ
     *
     * @param CycleTimeService $service サイクルタイムサービス
     */
    public function __construct(private readonly CycleTimeService $service)
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
        return __('yokakit.cycle_time');
    }

    // /**
    //  * Display a listing of the resource.
    //  *
    //  * @return Response
    //  */
    // public function index()
    // {
    //     //
    // }

    /**
     * サイクルタイム追加画面を表示する。
     *
     * @param Process $process 工程
     * @return View
     */
    public function create(Process $process): View
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $partNumbers = $this->service->unusedPartNumberOptions($process->process_id);
        return view('process.cycle-time.create', ['process' => $process, 'partNumbers' => $partNumbers]);
    }

    /**
     * サイクルタイムを追加する。
     *
     * @param StoreCycleTimeRequest $request リクエスト
     * @param Process $process 工程
     * @return RedirectResponse
     */
    public function store(StoreCycleTimeRequest $request, Process $process): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $result = $this->service->store($request);
        return $this->redirectWithStore($result, 'process.show', ['process' => $process, 'tab' => 'part-number']);
    }

    // /**
    //  * Display the specified resource.
    //  *
    //  * @param CycleTime $cycleTime
    //  * @return Response
    //  */
    // public function show(CycleTime $cycleTime)
    // {
    //     //
    // }

    /**
     * サイクルタイム編集画面を表示する。
     *
     * @param CycleTime $cycleTime サイクルタイム
     * @param Process $process 工程
     * @return View
     */
    public function edit(Process $process, CycleTime $cycleTime): View
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        return view('process.cycle-time.edit', ['process' => $process, 'cycleTime' => $cycleTime]);
    }

    /**
     * サイクルタイムを更新する。
     *
     * @param UpdateCycleTimeRequest $request リクエスト
     * @param Process $process 工程
     * @param CycleTime $cycleTime サイクルタイム
     * @return RedirectResponse
     */
    public function update(UpdateCycleTimeRequest $request, Process $process, CycleTime $cycleTime): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $result = $this->service->update($request, $cycleTime);
        return $this->redirectWithUpdate($result, 'process.show', ['process' => $process, 'tab' => 'part-number']);
    }

    /**
     * サイクルタイムを削除する。
     *
     * @param Process $process 工程
     * @param CycleTime $cycleTime サイクルタイム
     * @return RedirectResponse
     */
    public function destroy(Process $process, CycleTime $cycleTime): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $result = $this->service->destroy($cycleTime);
        return $this->redirectWithDestroy($result, 'process.show', ['process' => $process, 'tab' => 'part-number']);
    }
}
