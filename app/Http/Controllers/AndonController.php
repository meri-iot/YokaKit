<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\AndonColumnSize;
use App\Enums\EasingType;
use App\Http\Requests\UpdateAndonConfigRequest;
use App\Services\AndonService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Log;

/**
 * アンドン表示用コントローラークラス
 */
class AndonController extends AbstractController
{
    /**
     * コンストラクタ
     *
     * @param AndonService $service アンドンサービス
     */
    public function __construct(private readonly AndonService $service)
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
        return __('yokakit.target_config', ['target' => __('yokakit.andon')]);
    }

    /**
     * アンドン表示画面を表示する。
     *
     * @return View
     */
    public function index(): View
    {
        $processes = $this->service->processes();
        $config = $this->service->andonConfig($this->currentUserId());
        return view('home', ['processes' => $processes, 'config' => $config]);
    }

    /**
     * アンドン設定編集画面を表示する。
     *
     * @return View
     */
    public function edit(): View
    {
        $processes = $this->service->processes();
        $config = $this->service->andonConfig($this->currentUserId());
        $columns = array_combine(AndonColumnSize::getValues(), AndonColumnSize::getValues());
        $easing = array_combine(EasingType::getValues(), EasingType::getValues());
        return view('andon.config', [
            'processes' => $processes,
            'config' => $config,
            'columns' => $columns,
            'easing' => $easing
        ]);
    }

    /**
     * アンドン設定を更新する。
     *
     * 更新を試行し、結果に応じてトースト付きでホームへリダイレクトする。
     *
     * @param  \App\Http\Requests\UpdateAndonConfigRequest $request
     * @return RedirectResponse
     */
    public function update(UpdateAndonConfigRequest $request): RedirectResponse
    {
        try {
            $this->service->update($request, $this->currentUserId());
            return $this->redirectWithUpdate(true, 'home');
        } catch (Exception $e) {
            Log::error($e->getMessage(), $e->getTrace());
            return $this->redirectWithUpdate(false, 'home');
        }
    }

    /**
     * 認証済みユーザー ID を返す。
     *
     * auth ミドルウェア下での利用を前提にしつつ、想定外の未認証を例外化する。
     *
     * @throws AuthorizationException
     */
    private function currentUserId(): int
    {
        $userId = auth()->id();

        if (!is_int($userId)) {
            throw new AuthorizationException();
        }

        return $userId;
    }
}
