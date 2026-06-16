<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePlannedOutageRequest;
use App\Http\Requests\UpdatePlannedOutageRequest;
use App\Models\PlannedOutage;
use App\Services\PlannedOutageService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * 計画停止時間コントローラー
 */
class PlannedOutageController extends AbstractController
{
    /**
     * コンストラクタ
     *
     * @param PlannedOutageService $service 計画停止時間サービス
     */
    public function __construct(private readonly PlannedOutageService $service)
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
        return __('yokakit.planned_outage');
    }

    /**
     * 計画停止時間一覧画面を表示する。
     *
     * @return View
     */
    public function index(): View
    {
        $plannedOutages = $this->service->all();
        return view('planned-outage.index', ['plannedOutages' => $plannedOutages]);
    }

    /**
     * 計画停止時間追加フォーム画面を表示する。管理者のみ使用可能。
     *
     * @return View
     */
    public function create(): View
    {
        $this->authorizeAdmin();
        return view('planned-outage.create');
    }

    /**
     * 計画停止時間を新規登録する。管理者のみ使用可能。
     *
     * @param StorePlannedOutageRequest $request リクエスト
     * @return RedirectResponse
     */
    public function store(StorePlannedOutageRequest $request): RedirectResponse
    {
        $this->authorizeAdmin();
        $result = $this->service->store($request);
        return $this->redirectWithStore($result, 'planned-outage.index');
    }

    // /**
    //  * Display the specified resource.
    //  *
    //  * @param PlannedOutage $plannedOutage
    //  * @return Response
    //  */
    // public function show(PlannedOutage $plannedOutage)
    // {
    //     //
    // }

    /**
     * 計画停止時間編集フォーム画面を表示する。管理者のみ使用可能。
     *
     * @param PlannedOutage $plannedOutage
     * @return View
     */
    public function edit(PlannedOutage $plannedOutage): View
    {
        $this->authorizeAdmin();
        return view('planned-outage.edit', ['plannedOutage' => $plannedOutage]);
    }

    /**
     * 計画停止時間を更新する。管理者のみ使用可能。
     *
     * @param UpdatePlannedOutageRequest $request リクエスト
     * @param PlannedOutage $plannedOutage
     * @return RedirectResponse
     */
    public function update(UpdatePlannedOutageRequest $request, PlannedOutage $plannedOutage): RedirectResponse
    {
        $this->authorizeAdmin();
        $result = $this->service->update($request, $plannedOutage);
        return $this->redirectWithUpdate($result, 'planned-outage.index');
    }

    /**
     * 計画停止時間を削除する。管理者のみ使用可能。
     *
     * @param PlannedOutage $plannedOutage
     * @return RedirectResponse
     */
    public function destroy(PlannedOutage $plannedOutage): RedirectResponse
    {
        $this->authorizeAdmin();
        $result = $this->service->destroy($plannedOutage);
        return $this->redirectWithDestroy($result, 'planned-outage.index');
    }
}
