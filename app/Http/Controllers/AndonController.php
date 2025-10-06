<?php

namespace App\Http\Controllers;

use App\Enums\AndonColumnSize;
use App\Enums\EasingType;
use App\Http\Requests\UpdateAndonConfigRequest;
use App\Services\AndonService;
use Exception;
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
     * Display a listing of the resource.
     *
     * @return View
     */
    public function index(): View
    {
        $processes = $this->service->processes();
        $config = $this->service->andonConfig();
        return view('home', ['processes' => $processes, 'config' => $config]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @return View
     */
    public function edit(): View
    {
        $processes = $this->service->processes();
        $config = $this->service->andonConfig();
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
     * Update the specified resource in storage.
     *
     * @param  \App\Http\Requests\UpdateAndonConfigRequest $request
     * @return RedirectResponse
     */
    public function update(UpdateAndonConfigRequest $request): RedirectResponse
    {
        try {
            $this->service->update($request);
            return $this->redirectWithUpdate(true, 'home');
        } catch (Exception $e) {
            Log::error($e->getMessage(), $e->getTrace());
            return $this->redirectWithUpdate(false, 'home');
        }
    }
}
