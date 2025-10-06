<?php

namespace App\Services;

use App\Http\Requests\UpdateAndonConfigRequest;
use App\Models\AndonConfig;
use App\Models\Process;
use App\Repositories\AndonConfigRepository;
use App\Repositories\AndonLayoutRepository;
use App\Repositories\ProcessRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * アンドンサービス
 */
class AndonService
{
    private readonly AndonConfigRepository $andonConfig;
    private readonly AndonLayoutRepository $andonLayout;
    private readonly ProcessRepository $process;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->andonConfig = App::make(AndonConfigRepository::class);
        $this->andonLayout = App::make(AndonLayoutRepository::class);
        $this->process = App::make(ProcessRepository::class);
    }

    /**
     * すべての工程および関連するアンドン設定とセンサーイベントを取得する
     *
     * @return Collection<int,Process>
     */
    public function processes(): Collection
    {
        /** @var Collection<int,Process> */
        $processes = $this->process
            ->all([
                'andonLayout',
                'sensorEvents',
                'productionHistory.indicatorLine.payload',
            ])
            ->map(function (Process $p) {
                if (!is_null($p->productionHistory)) {
                    $payloadData = $p->productionHistory->indicatorLine->payload->getPayloadData();
                    $p->production_summary = $p->productionHistory->makeProductionSummary($payloadData);
                }
                return $p;
            })
            ->sortBy([
                ['andonLayout.order', 'asc'],
                ['process_id', 'asc'],
            ])
            ->values();
        return $processes;
    }

    /**
     * すべての工程の指標を取得する
     *
     * @return Collection<int,Process>
     */
    public function indicators(): Collection
    {
        /** @var Collection<int,Process> */
        $processes = $this->process
            ->all([
                'productionHistory.indicatorLine.payload',
            ])
            ->map(function (Process $p) {
                if (!is_null($p->productionHistory)) {
                    $payloadData = $p->productionHistory->indicatorLine->payload->getPayloadData();
                    $p->summary = $p->productionHistory->makeIndicatorSummary($payloadData);
                }
                return $p;
            })
            ->sortBy([
                ['process_id', 'asc'],
            ])
            ->values();
        return $processes;
    }

    /**
     * アンドン設定を取得する
     *
     * @return AndonConfig
     */
    public function andonConfig(): AndonConfig
    {
        return $this->andonConfig->andonConfig();
    }

    /**
     * アンドン設定を更新する
     *
     * @param UpdateAndonConfigRequest $request
     * @throws ModelNotFoundException
     */
    public function update(UpdateAndonConfigRequest $request): void
    {
        DB::transaction(function () use ($request) {
            $config = $this->andonConfig();
            $result = $this->andonConfig->update($request, $config);
            Utility::throwIfException($config, $result);
            if (!is_null($request->layouts)) {
                if (!$this->andonLayout->updateLayouts($request->layouts, Auth::id())) {
                    throw new ModelNotFoundException();
                }
            }
        });
    }
}
