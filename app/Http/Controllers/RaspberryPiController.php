<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreRaspberryPiRequest;
use App\Http\Requests\UpdateRaspberryPiRequest;
use App\Models\RaspberryPi;
use App\Services\RaspberryPiService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * ラズベリーパイコントローラー
 */
class RaspberryPiController extends AbstractController
{
    /**
     * コンストラクタ
     *
     * @param RaspberryPiService $service ラズベリーパイサービス
     */
    public function __construct(private readonly RaspberryPiService $service)
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
        return __('yokakit.raspberry_pi');
    }

    /**
     * ラズベリーパイ一覧画面を表示する。
     *
     * @return View
     */
    public function index(): View
    {
        $raspberryPis = $this->service->all();
        return view('raspberry-pi.index', ['raspberryPis' => $raspberryPis]);
    }

    /**
     * ラズベリーパイ追加フォーム画面を表示する。管理者のみ使用可能。
     *
     * @return View
     */
    public function create(): View
    {
        $this->authorizeAdmin();
        return view('raspberry-pi.create');
    }

    /**
     * ラズベリーパイを新規登録する。管理者のみ使用可能。
     *
     * @param StoreRaspberryPiRequest $request リクエスト
     * @return RedirectResponse
     */
    public function store(StoreRaspberryPiRequest $request): RedirectResponse
    {
        $this->authorizeAdmin();
        $result = $this->service->store($request);
        return $this->redirectWithStore($result, 'raspberry-pi.index');
    }

    // /**
    //  * Display the specified resource.
    //  *
    //  * @param RaspberryPi $raspberryPi
    //  * @return Response
    //  */
    // public function show(RaspberryPi $raspberryPi)
    // {
    //     //
    // }

    /**
     * ラズベリーパイ編集フォーム画面を表示する。管理者のみ使用可能。
     *
     * @param RaspberryPi $raspberryPi
     * @return View
     */
    public function edit(RaspberryPi $raspberryPi): View
    {
        $this->authorizeAdmin();
        return view('raspberry-pi.edit', ['raspberryPi' => $raspberryPi]);
    }

    /**
     * ラズベリーパイを更新する。管理者のみ使用可能。
     *
     * @param UpdateRaspberryPiRequest $request リクエスト
     * @param RaspberryPi $raspberryPi
     * @return RedirectResponse
     */
    public function update(UpdateRaspberryPiRequest $request, RaspberryPi $raspberryPi): RedirectResponse
    {
        $this->authorizeAdmin();
        $result = $this->service->update($request, $raspberryPi);
        return $this->redirectWithUpdate($result, 'raspberry-pi.index');
    }

    /**
     * ラズベリーパイを削除する。管理者のみ使用可能。
     *
     * @param RaspberryPi $raspberryPi
     * @return RedirectResponse
     */
    public function destroy(RaspberryPi $raspberryPi): RedirectResponse
    {
        $this->authorizeAdmin();
        $result = $this->service->destroy($raspberryPi);
        return $this->redirectWithDestroy($result, 'raspberry-pi.index');
    }
}
