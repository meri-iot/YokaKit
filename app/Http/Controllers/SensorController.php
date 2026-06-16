<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\StoreSensorRequest;
use App\Http\Requests\UpdateSensorRequest;
use App\Models\Process;
use App\Models\Sensor;
use App\Services\SensorService;
use App\Services\Utility;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;

/**
 * センサーコントローラー
 */
class SensorController extends AbstractController
{
    /**
     * コンストラクタ
     *
     * @param SensorService $service センサーサービス
     */
    public function __construct(private readonly SensorService $service)
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
        return __('yokakit.alarm');
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
     * アラームセンサー追加フォーム画面を表示する。管理者のみ使用可能。
     *
     * @param Process $process
     * @return View
     */
    public function create(Process $process): View
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $raspberryPiOptions = $this->service->raspberryPiOptions();
        $pinOptions = Utility::pinNumberOptions();
        return view('process.alarm.create', [
            'process' => $process,
            'raspberryPiOptions' => $raspberryPiOptions,
            'pinOptions' => $pinOptions,
        ]);
    }

    /**
     * アラームセンサーを新規登録する。管理者のみ使用可能。
     *
     * @param StoreSensorRequest $request リクエスト
     * @param Process $process
     * @return RedirectResponse
     */
    public function store(StoreSensorRequest $request, Process $process): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $result = $this->service->store($request);
        return $this->redirectWithStore($result, 'process.show', ['process' => $process, 'tab' => 'alarm']);
    }

    // /**
    //  * Display the specified resource.
    //  *
    //  * @param Process $process 工程
    //  * @param Sensor $sensor
    //  * @return Response
    //  */
    // public function show(Process $process, Sensor $sensor)
    // {
    //     //
    // }

    /**
     * アラームセンサー編集フォーム画面を表示する。管理者のみ使用可能。
     *
     * @param Process $process 工程
     * @param Sensor $sensor
     * @return View
     */
    public function edit(Process $process, Sensor $sensor): View
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $raspberryPiOptions = $this->service->raspberryPiOptions();
        $pinOptions = Utility::pinNumberOptions();
        return view('process.alarm.edit', [
            'process' => $process,
            'sensor' => $sensor,
            'raspberryPiOptions' => $raspberryPiOptions,
            'pinOptions' => $pinOptions,
        ]);
    }

    /**
     * アラームセンサーを更新する。管理者のみ使用可能。
     *
     * @param UpdateSensorRequest $request リクエスト
     * @param Process $process 工程
     * @param Sensor $sensor
     * @return RedirectResponse
     */
    public function update(UpdateSensorRequest $request, Process $process, Sensor $sensor): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $result = $this->service->update($request, $sensor);
        return $this->redirectWithUpdate($result, 'process.show', ['process' => $process, 'tab' => 'alarm']);
    }

    /**
     * アラームセンサーを削除する。管理者のみ使用可能。
     *
     * @param Process $process 工程
     * @param Sensor $sensor
     * @return RedirectResponse
     */
    public function destroy(Process $process, Sensor $sensor): RedirectResponse
    {
        $this->authorizeAdmin();
        $this->throwExceptionIfRunning($process);
        $result = $this->service->destroy($sensor);
        return $this->redirectWithDestroy($result, 'process.show', ['process' => $process, 'tab' => 'alarm']);
    }
}
