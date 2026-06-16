<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StorePartNumberRequest;
use App\Http\Requests\UpdatePartNumberRequest;
use App\Models\PartNumber;
use App\Services\PartNumberService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * 品番コントローラー
 */
class PartNumberController extends AbstractController
{
    /**
     * コンストラクタ
     *
     * @param PartNumberService $service 品番サービス
     */
    public function __construct(private readonly PartNumberService $service)
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
        return __('yokakit.part_number');
    }

    /**
     * 品番一覧画面を表示する。
     *
     * @return View
     */
    public function index(): View
    {
        $partNumbers = $this->service->all();
        return view('part-number.index', ['partNumbers' => $partNumbers]);
    }

    /**
     * 品番追加フォーム画面を表示する。管理者のみ使用可能。
     *
     * @return View
     */
    public function create(): View
    {
        $this->authorizeAdmin();
        return view('part-number.create');
    }

    /**
     * 品番を新規登録する。管理者のみ使用可能。
     *
     * @param StorePartNumberRequest $request リクエスト
     * @return RedirectResponse
     */
    public function store(StorePartNumberRequest $request): RedirectResponse
    {
        $this->authorizeAdmin();
        $result = $this->service->store($request);
        return $this->redirectWithStore($result, 'part-number.index');
    }

    // /**
    //  * Display the specified resource.
    //  *
    //  * @param PartNumber $partNumber
    //  * @return Response
    //  */
    // public function show(PartNumber $partNumber)
    // {
    //     //
    // }

    /**
     * 品番編集フォーム画面を表示する。管理者のみ使用可能。
     *
     * @param PartNumber $partNumber
     * @return View
     */
    public function edit(PartNumber $partNumber): View
    {
        $this->authorizeAdmin();
        return view('part-number.edit', ['partNumber' => $partNumber]);
    }

    /**
     * 品番を更新する。管理者のみ使用可能。
     *
     * @param UpdatePartNumberRequest $request リクエスト
     * @param PartNumber $partNumber
     * @return RedirectResponse
     */
    public function update(UpdatePartNumberRequest $request, PartNumber $partNumber): RedirectResponse
    {
        $this->authorizeAdmin();
        $result = $this->service->update($request, $partNumber);
        return $this->redirectWithUpdate($result, 'part-number.index');
    }

    /**
     * 品番を削除する。管理者のみ使用可能。
     *
     * @param PartNumber $partNumber
     * @return RedirectResponse
     */
    public function destroy(PartNumber $partNumber): RedirectResponse
    {
        $this->authorizeAdmin();
        $result = $this->service->destroy($partNumber);
        return $this->redirectWithDestroy($result, 'part-number.index');
    }
}
