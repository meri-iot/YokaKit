<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductionStatus;
use App\Events\ProductionSummaryNotification;
use App\Exceptions\NoIndicatorException;
use App\Http\Requests\StopProductionRequest;
use App\Http\Requests\StoreProductionHistoryRequest;
use App\Http\Requests\SwitchPartNumberRequestFromApi;
use App\Jobs\BreakdownJudgeJob;
use App\Jobs\ChangeoverJob;
use App\Jobs\PlanCountJob;
use App\Jobs\StopJob;
use App\Models\CycleTime;
use App\Models\Process;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use App\Models\RaspberryPi;
use App\Repositories\CycleTimeRepository;
use App\Repositories\DefectiveProductionRepository;
use App\Repositories\PartNumberRepository;
use App\Repositories\PayloadRepository;
use App\Repositories\ProcessRepository;
use App\Repositories\ProducerRepository;
use App\Repositories\ProductionPlannedOutageRepository;
use App\Repositories\ProductionHistoryRepository;
use App\Repositories\ProductionLineRepository;
use App\Repositories\ProductionRepository;
use App\Repositories\RaspberryPiRepository;
use App\Repositories\WorkerRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 生産履歴の検索・品番切替・停止処理を扱うサービス
 */
class ProductionHistoryService
{
    /**
     * コンストラクタ
     */
    public function __construct(
        private readonly CycleTimeRepository $cycleTime,
        private readonly DefectiveProductionRepository $defectiveProduction,
        private readonly PartNumberRepository $partNumber,
        private readonly PayloadRepository $payload,
        private readonly ProcessRepository $process,
        private readonly ProductionHistoryRepository $productionHistory,
        private readonly ProducerRepository $producer,
        private readonly ProductionRepository $production,
        private readonly ProductionLineRepository $productionLine,
        private readonly ProductionPlannedOutageRepository $productionPlannedOutage,
        private readonly RaspberryPiRepository $raspberryPi,
        private readonly WorkerRepository $worker,
    ) {}

    /**
     * 指定した工程IDの品番選択用のオプションを取得する
     *
     * @param Process $process 工程
     * @return array<int,string> 品番選択用のオプション
     */
    public function partNumberOptions(Process $process): array
    {
        return $this->cycleTime->notRunningPartNumberOptions($process);
    }

    /**
     * 指定した工程の生産履歴のすべての品番名選択用のオプションを取得する
     *
     * @param Process $process 工程
     * @return array<string,string> 品番選択用のオプション
     */
    public function productedPartNumberOptions(Process $process): array
    {
        return $this->productionHistory
            ->get(['process_id' => $process->process_id], order: 'part_number_name')
            ->reduce(function (array $carry, ProductionHistory $h) {
                $carry[$h->part_number_name] = $h->part_number_name;
                return $carry;
            }, []);
    }

    /**
     * 指定した工程IDの生産履歴を取得する
     *
     * @param int $processId 工程ID
     * @param string|null $partNumberName 品番名
     * @param string|null $startDate 開始日
     * @param string|null $endDate 終了日
     * @param int $page ページあたりの件数
     * @return LengthAwarePaginator<ProductionHistory>
     */
    public function histories(int $processId, string|null $partNumberName, string|null $startDate, string|null $endDate, int $page = 10): LengthAwarePaginator
    {
        return $this->productionHistory->histories($processId, $partNumberName, $startDate, $endDate, $page);
    }

    /**
     * 生産履歴の詳細データを取得する
     *
     * @param int $historyId 生産履歴ID
     * @return Collection<int,ProductionLine>
     */
    public function productionLines(int $historyId): Collection
    {
        return $this->productionLine->get(
            ['production_history_id' => $historyId],
            ['productions', 'defectiveProductions'],
            order: 'order'
        );
    }

    /**
     * 品番切り替えをWebブラウザから実行する
     *
     * @param StoreProductionHistoryRequest $request 品番切り替えリクエスト
     * @param Process $process 品番切り替え対象の工程
     * @return bool 成否
     * @throws NoIndicatorException 生産指標が設定されていないエラー
     * @throws ModelNotFoundException
     */
    public function switchPartNumberFromForm(StoreProductionHistoryRequest $request, Process $process): bool
    {
        // ラインがなければ終了
        if ($process->raspberryPis->count() === 0) {
            Log::warning('Line not found.', $process->toArray());
            return false;
        }
        // サイクルタイムが削除されていれば終了
        $cycleTime = $this->findCycleTime($process->process_id, $request->part_number_id);
        if (is_null($cycleTime)) {
            Log::warning('CycleTime not found.', $request->all());
            return false;
        }

        // 工程が稼働中であれば停止する
        if ($process->isRunning()) {
            $this->stop($process, false);
        }

        // 変更するステータス
        $status = $request->status;
        // 目標値
        $goal = $request->goal;
        // 品番切り替え実行
        $this->switchPartNumber($status, $process, $cycleTime, $goal);
        return true;
    }

    /**
     * 品番切り替えをWebAPIから実行する
     *
     * @param SwitchPartNumberRequestFromApi $request 品番切り替えリクエスト
     * @return bool 成否
     */
    public function switchPartNumberFromApi(SwitchPartNumberRequestFromApi $request): bool
    {
        $processName = $request->json('processName');
        $partNumberName = $request->json('partNumberName');
        $goal = $request->json('goal');
        $force = $request->json('force');
        $changeover = $request->json('changeover');

        $process = $this->process->first(['process_name' => $processName]);
        $partNumber = $this->partNumber->first(['part_number_name' => $partNumberName]);
        if (is_null($process) || is_null($partNumber)) {
            Log::warning('Process or PartNumber not found', $request->all());
            return false;
        }
        $cycleTime = $this->findCycleTime($process->process_id, $partNumber->part_number_id);
        if (is_null($cycleTime)) {
            Log::warning('CycleTime not found', $request->all());
            return false;
        }
        $status = $changeover ? ProductionStatus::CHANGEOVER() : ProductionStatus::RUNNING();
        if ($process->isStopped()) {
            $this->switchPartNumber($status, $process, $cycleTime, $goal);
            return true;
        } else if ($force) {
            $this->stop($process, false);
            $this->switchPartNumber($status, $process, $cycleTime, $goal);
            return true;
        } else {
            return false;
        }
    }

    /**
     * 品番切り替えをMQTTから実行する
     *
     * @param string $ipAddress IPアドレス
     * @param string $macAddress MACアドレス
     * @param string $barcode バーコード
     * @return bool 成否
     */
    public function switchPartNumberFromMqtt(string $ipAddress, string $macAddress, string $barcode): bool
    {
        $processName = $this->searchProcessName($ipAddress, $macAddress);
        if (is_null($processName)) {
            return false;
        }
        $process = $this->process->first(['process_name' => $processName]);
        $partNumber = $this->partNumber->first(['barcode' => $barcode]);
        if (is_null($process) || is_null($partNumber)) {
            Log::warning('Process or PartNumber not found');
            return false;
        }
        $cycleTime = $this->findCycleTime($process->process_id, $partNumber->part_number_id);
        if (is_null($cycleTime)) {
            Log::warning('CycleTime not found');
            return false;
        }
        if ($process->productionHistory?->part_number_name === $partNumber->part_number_name) {
            Log::warning('Part is already producing');
            return false;
        } else {
            if ($process->isRunning()) {
                $this->stop($process, false);
            }
            $this->switchPartNumber(ProductionStatus::CHANGEOVER(), $process, $cycleTime);
            return true;
        }
    }

    /**
     * 指定したIPアドレスとMACアドレスから品番切り替え対象の工程名を取得する
     *
     * @param string $ipAddress IPアドレス
     * @param string $macAddress MACアドレス
     * @return string|null 工程名 (見つからない場合はnull)
     */
    private function searchProcessName(string $ipAddress, string $macAddress): ?string
    {
        $raspi = $this->raspberryPi->first(['ip_address' => $ipAddress], 'processes');
        if (is_null($raspi)) {
            Log::warning('Raspi not found', [$ipAddress]);
            return null;
        }
        $processNames = $raspi->processes->map(fn($x) => $x->process_name)->unique();
        if ($processNames->count() != 1) {
            // 工程が複数
            $worker = $this->worker->first(['mac_address' => $macAddress], 'processes');
            if (is_null($worker)) {
                Log::warning('Worker not found', [$macAddress]);
                return null;
            }
            $processNames = $worker->processes->map(fn($x) => $x->process_name)->unique();
            if ($processNames->count() != 1) {
                Log::warning('Process cannot determine', [$macAddress]);
                return null;
            }
        }
        return $processNames->first();
    }

    /**
     * 工程IDと品番IDからサイクルタイムを取得する
     *
     * @param int $processId 工程ID
     * @param int $partNumberId 品番ID
     * @return CycleTime|null サイクルタイム
     */
    private function findCycleTime(int $processId, int $partNumberId): ?CycleTime
    {
        return $this->cycleTime->first([
            'process_id' => $processId,
            'part_number_id' => $partNumberId,
        ]);
    }

    /**
     * 品番切り替えを行う
     *
     * @param ProductionStatus $status 品番切り替え後のステータス
     * @param Process $process 対象の工程
     * @param CycleTime $cycleTime 対象のサイクルタイム
     * @param int|null $goal 目標値
     * @return void
     */
    private function switchPartNumber(ProductionStatus $status, Process $process, CycleTime $cycleTime, ?int $goal = null): void
    {
        DB::transaction(function () use ($status, $process, $cycleTime, $goal) {
            // 生産履歴レコード作成
            $productionHistory = $this->productionHistory->storeHistory($process, $cycleTime, $status, $goal);
            Utility::ensureModelExists($productionHistory);

            $id = $productionHistory->production_history_id;
            $isChangeover = $status->is(ProductionStatus::CHANGEOVER());
            // 生産計画停止時間レコード作成
            $this->storeProductionPlannedOutage($process, $id);
            // 生産ラインレコード作成
            $this->storeFirstProductionLine($process, $productionHistory, Utility::now(), $isChangeover);
            // 工程に稼働品番を設定
            $result = $this->process->start($process, $id);
            Utility::ensureOperationSucceeded($process, $result);
        });
    }

    /**
     * 生産を停止する
     *
     * @param Process $process 停止対象の工程
     * @param bool $isDispatchEvent イベントを通知するかどうか
     * @return void
     */
    public function stop(Process $process, bool $isDispatchEvent): void
    {
        DB::transaction(function () use ($process, $isDispatchEvent) {
            $history = $process->productionHistory;
            Utility::ensureModelExists($history);
            $now = Utility::now();
            $result = $this->productionHistory->stop($history, $now);
            Utility::ensureOperationSucceeded($history, $result);
            $this->producer->stop($history->productionLines, $now);
            $result = $this->process->stop($process);
            Utility::ensureOperationSucceeded($process, $result);

            // コミット後に停止ジョブを投入する
            DB::afterCommit(function () use ($history, $now, $isDispatchEvent) {
                StopJob::dispatch($history->production_history_id, $now, $isDispatchEvent);
            });
        });
    }

    /**
     * 生産をWebAPIより停止する
     *
     * @param StopProductionRequest $request 停止リクエスト
     * @return void
     */
    public function stopFromApi(StopProductionRequest $request): void
    {
        $processName = $request->json('processName');
        $process = $this->process->first(['process_name' => $processName]);
        Utility::ensureModelExists($process);
        $this->stop($process, true);
    }

    /**
     * 段取替えを実施する
     *
     * @param Process $process 段取替え対象の工程
     * @return bool 成否
     * @throws ModelNotFoundException
     */
    public function changeover(Process $process): bool
    {
        if ($process->isStopped()) {
            Log::warning('Process is COMPLETE.', $process->toArray());
            return false;
        }

        DB::transaction(function () use ($process) {

            $history = $process->productionHistory;
            Utility::ensureModelExists($history);

            $isChangeover = $process->isChangeover();
            $nextStatus = $isChangeover ? ProductionStatus::RUNNING() : ProductionStatus::CHANGEOVER();
            $now = Utility::now();

            // ステータスを段取り替え、または稼働に切り替え
            $this->productionHistory->updateStatus($history, $nextStatus);

            // 段取り替えの開始/終了ジョブを登録
            DB::afterCommit(function () use ($history, $now, $isChangeover) {
                ChangeoverJob::dispatch($history->production_history_id, $now, !$isChangeover);
            });
        });

        return true;
    }

    /**
     * 稼働ラインを新規に登録する
     *
     * @param Process $process 工程
     * @param ProductionHistory $history 稼働履歴
     * @param Carbon $date 時刻
     * @param bool $changeover 段取り替えの有無
     * @return void
     * @throws NoIndicatorException 指標となるラインがない
     * @throws ModelNotFoundException DBに対象データが存在しない
     */
    private function storeFirstProductionLine(Process $process, ProductionHistory $history, Carbon $date, bool $changeover): void
    {
        $indicatorIndex = $process->raspberryPis
            ->reverse()
            ->filter(fn(RaspberryPi $x) => !is_null($x->pivot))
            ->filter(fn(RaspberryPi $x) => $x->pivot->defective === false)
            ->keys()
            ->first();

        if (is_null($indicatorIndex)) {
            throw new NoIndicatorException();
        }

        $historyId = $history->production_history_id;
        $countSwitch = $history->count_switch;
        $cycleTimeMs = $history->cycleTimeMs();
        $plannedOutages = $this->productionPlannedOutage->getStartEndAsArray($historyId);

        foreach ($process->raspberryPis as $index => $raspi) {
            if (is_null($raspi->pivot)) {
                continue;
            }
            if ($raspi->pivot->defective === true) {
                continue;
            }
            // 加工数量ラインを登録
            $indicator = $index === $indicatorIndex;
            $pl = $this->productionLine->save($raspi->pivot, $historyId, $raspi->ip_address, $indicator);
            Utility::ensureModelExists($pl);

            // 生産者を登録
            $productionLineId = $pl->production_line_id;
            if (!is_null($raspi->pivot->worker_id)) {
                $worker = $this->worker->find($raspi->pivot->worker_id);
                Utility::ensureModelExists($worker);
                $this->producer->save($worker, $productionLineId, $date);
            }

            $defectiveLineIds = [];
            foreach ($raspi->pivot->defectiveLines as $defectiveLine) {
                // 不良品ラインを登録
                $defectiveProductionLine = $this->productionLine->saveDefectiveLine($defectiveLine, $historyId, $defectiveLine->raspberryPi->ip_address, $productionLineId);
                Utility::ensureModelExists($defectiveProductionLine);
                // 不良品のカウントを登録
                $defectiveProduction = $this->defectiveProduction->save($defectiveProductionLine->production_line_id, 0, $date);
                Utility::ensureModelExists($defectiveProduction);
                // 不良品ラインをペイロードに与えるために配列に格納しておく
                array_push($defectiveLineIds, $defectiveProductionLine->production_line_id);
            }

            // 指標計算用のペイロードを登録
            $overTimeMs = $history->overTimeMs();
            $payload = $this->payload->create($productionLineId, $defectiveLineIds, $date, $plannedOutages, $changeover, $countSwitch, $cycleTimeMs, $overTimeMs, $indicator);

            // 生産カウントを登録
            $payloadData = $payload->getPayloadData();
            $production = $this->production->save($productionLineId, $payloadData);
            Utility::ensureModelExists($production);

            if ($indicator  === true) {
                // 指標となるラインの場合にサマリーを通知
                DB::afterCommit(function () use ($history, $payload) {
                    ProductionSummaryNotification::dispatch($this->productionHistory->makeProductionSummary($history, $payload->getPayloadData()));
                });

                if ($changeover === false) {
                    // 段取り替えでない場合にチョコ停判定ジョブを登録
                    DB::afterCommit(function () use ($overTimeMs, $production) {
                        BreakdownJudgeJob::delayedDispatch($overTimeMs, $production);
                    });
                }
            }

            // 計画値カウントジョブを登録
            $delay = $date->copy()->addMilliseconds($cycleTimeMs);
            DB::afterCommit(function () use ($productionLineId, $cycleTimeMs, $delay, $changeover, $payloadData) {
                PlanCountJob::dispatch($productionLineId, $cycleTimeMs, $delay, $changeover, $payloadData->jobKey)->delay($delay);
            });
        }
    }

    /**
     * 稼働計画停止時間を登録する
     *
     * @param Process $process 工程
     * @param int $productionHistoryId 稼働履歴ID
     * @return void
     * @throws ModelNotFoundException DBに対象データが存在しない
     */
    private function storeProductionPlannedOutage(Process $process, int $productionHistoryId): void
    {
        foreach ($process->plannedOutages as $plannedOutage) {
            $result = $this->productionPlannedOutage->save($plannedOutage, $productionHistoryId);
            Utility::ensureOperationSucceeded($plannedOutage, $result);
        }
    }

    /**
     * 指定したIDの生産履歴を削除する
     *
     * @param array<string|int> $productionHistoryIds 生産履歴IDの配列
     * @return int 削除件数
     */
    public function destroyHistories(array $productionHistoryIds): int
    {
        return $this->productionHistory->softDelete($productionHistoryIds);
    }
}
