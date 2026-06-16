<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreProcessPlannedOutageRequest;
use App\Models\Process;
use App\Models\ProcessPlannedOutage;
use App\Services\ProcessPlannedOutageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * 工程計画停止時間コントローラー
 */
class ProcessPlannedOutageController extends AbstractController
{
    /**
     * コンストラクタ
     *
     * @param ProcessPlannedOutageService $service 工程計画停止時間サービス
     */
    public function __construct(private readonly ProcessPlannedOutageService $service)
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
        return __('yokakit.process_planned_outage');
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
     * 工程計画停止時間の追加フォーム画面を表示する。管理者のみ使用可能。
     *
     * @param Process $process 工程
     * @return View
     */
    public function create(Process $process): View
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $plannedOutages = $this->service->unusedPlannedOutageOptions($process->process_id);
        return view('process.planned-outage.create', ['process' => $process, 'plannedOutages' => $plannedOutages]);
    }

    /**
     * 工程計画停止時間を新規登録する。管理者のみ使用可能。
     *
     * @param StoreProcessPlannedOutageRequest $request リクエスト
     * @param Process $process 工程
     * @return RedirectResponse
     */
    public function store(StoreProcessPlannedOutageRequest $request, Process $process): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $result = $this->service->store($request);
        return $this->redirectWithStore($result, 'process.show', ['process' => $process, 'tab' => 'planned-outage']);
    }

    // /**
    //  * Display the specified resource.
    //  *
    //  * @param ProcessPlannedOutage $processPlannedOutage
    //  * @param Process $process 工程
    //  * @return Response
    //  */
    // public function show(ProcessPlannedOutage $processPlannedOutage, Process $process)
    // {
    //     //
    // }

    // /**
    //  * Show the form for editing the specified resource.
    //  *
    //  * @param ProcessPlannedOutage $processPlannedOutage
    //  * @param Process $process 工程
    //  * @return Response
    //  */
    // public function edit(ProcessPlannedOutage $processPlannedOutage, Process $process)
    // {
    //     //
    // }

    // /**
    //  * Update the specified resource in storage.
    //  *
    //  * @param Process $process 工程
    //  * @param ProcessPlannedOutage $processPlannedOutage
    //  * @return Response
    //  */
    // public function update(/*UpdateProcessPlannedOutageRequest $request,*/Process $process, ProcessPlannedOutage $processPlannedOutage)
    // {
    //     //
    // }

    /**
     * 工程計画停止時間を削除する。管理者のみ使用可能。
     *
     * @param Process $process 工程
     * @param ProcessPlannedOutage $processPlannedOutage
     * @return RedirectResponse
     */
    public function destroy(Process $process, ProcessPlannedOutage $processPlannedOutage): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $result = $this->service->destroy($processPlannedOutage);
        return $this->redirectWithDestroy($result, 'process.show', ['process' => $process, 'tab' => 'planned-outage']);
    }
}
