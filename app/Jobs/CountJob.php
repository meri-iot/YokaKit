<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Data\PayloadData;
use App\Enums\ProductionStatus;
use App\Events\ProductionSummaryNotification;
use App\Models\Production;
use App\Models\ProductionHistory;
use App\Models\ProductionLine;
use App\Repositories\DefectiveProductionRepository;
use App\Repositories\PayloadRepository;
use App\Repositories\ProductionHistoryRepository;
use App\Repositories\ProductionLineRepository;
use App\Repositories\ProductionRepository;
use App\Services\Utility;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 生産数カウントジョブ
 */
class CountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 生産数カウントジョブのインスタンスを作成する。
     *
     * @return void
     */
    public function __construct(
        private readonly int $productionLineId,
        private readonly int $count,
        private readonly Carbon $date
    ) {
        Log::info('Dispatch CountJob', [
            'date' => Utility::format($this->date),
            'productionLineId' => $this->productionLineId,
            'count' => $this->count,
        ]);
    }

    /**
     * 生産数カウントジョブを実行する
     *
     * Queue Worker が handle 引数へ依存解決した Repository を注入する。
     *
     * @param ProductionLineRepository $productionLineRepository
     * @param ProductionHistoryRepository $productionHistoryRepository
     * @param PayloadRepository $payloadRepository
     * @param ProductionRepository $productionRepository
     * @param DefectiveProductionRepository $defectiveProductionRepository
     * @return void
     */
    public function handle(
        ProductionLineRepository $productionLineRepository,
        ProductionHistoryRepository $productionHistoryRepository,
        PayloadRepository $payloadRepository,
        ProductionRepository $productionRepository,
        DefectiveProductionRepository $defectiveProductionRepository,
    ): void {
        Log::info('Execute CountJob', [
            'date' => Utility::format($this->date),
            'productionLineId' => $this->productionLineId,
            'count' => $this->count,
        ]);
        DB::transaction(function () use (
            $productionLineRepository,
            $productionHistoryRepository,
            $payloadRepository,
            $productionRepository,
            $defectiveProductionRepository,
        ) {

            // 生産ラインデータを取得
            $productionLine = $productionLineRepository->find($this->productionLineId, ['productionHistory']);
            Utility::ensureModelExists($productionLine);

            // ペイロードとデータを取得
            $payload = $payloadRepository->getPayload($productionLine);
            $payloadData = $payload->getPayloadData();

            $history = $productionLine->productionHistory;

            switch ($payloadData->status()) {
                case ProductionStatus::RUNNING():
                    $this->handleRunningState(
                        $productionLine,
                        $history,
                        $payload,
                        $payloadRepository,
                        $productionRepository,
                        $defectiveProductionRepository,
                        $productionHistoryRepository,
                    );
                    break;
                case ProductionStatus::CHANGEOVER():
                    $this->handleChangeoverState($history, $productionHistoryRepository);
                    break;
                case ProductionStatus::BREAKDOWN():
                    $this->handleBreakdownState($productionLine, $history, $productionHistoryRepository);
                    break;
                default:
                    break;
            }
        });
    }

    /**
     * RUNNING状態の更新処理を実行する。
     *
     * @param ProductionLine $productionLine
     * @param ProductionHistory $history
     * @param object $payload
     * @param PayloadRepository $payloadRepository
     * @param ProductionRepository $productionRepository
     * @param DefectiveProductionRepository $defectiveProductionRepository
     * @return void
     */
    private function handleRunningState(
        ProductionLine $productionLine,
        ProductionHistory $history,
        object $payload,
        PayloadRepository $payloadRepository,
        ProductionRepository $productionRepository,
        DefectiveProductionRepository $defectiveProductionRepository,
        ProductionHistoryRepository $productionHistoryRepository,
    ): void {
        $payloadData = $this->updateRunningPayload(
            $productionLine,
            $payload,
            $payloadRepository,
            $defectiveProductionRepository,
        );

        $production = $productionRepository->save($this->productionLineId, $payloadData);
        Utility::ensureModelExists($production);

        if ($productionLine->indicator && !$productionLine->defective) {
            // 指標ラインかつ通常ラインの場合のみチョコ停判定ジョブを登録する。
            BreakdownJudgeJob::delayedDispatch($history->overTimeMs(), $production);
        }

        // 通知はコミット後に行い、未コミットデータを参照しないようにする。
        DB::afterCommit(static function () use ($productionHistoryRepository, $history, $payloadData): void {
            ProductionSummaryNotification::dispatch($productionHistoryRepository->makeProductionSummary($history, $payloadData));
        });
    }

    /**
     * RUNNING状態のペイロード更新処理を実行する。
     *
     * @param ProductionLine $productionLine
     * @param object $payload
     * @param PayloadRepository $payloadRepository
     * @param DefectiveProductionRepository $defectiveProductionRepository
     * @return PayloadData
     */
    private function updateRunningPayload(
        ProductionLine $productionLine,
        object $payload,
        PayloadRepository $payloadRepository,
        DefectiveProductionRepository $defectiveProductionRepository,
    ): PayloadData {
        if (!$productionLine->defective) {
            return $payloadRepository->updatePayload($payload, function (PayloadData $x): void {
                $x->count = $this->count;
                $x->update($this->date);
            });
        }

        $payloadData = $payloadRepository->updatePayload($payload, function (PayloadData $x): void {
            $x->setDefectiveCount($this->productionLineId, $this->count);
            $x->update($this->date);
        });

        $defectiveProduction = $defectiveProductionRepository->save($this->productionLineId, $this->count, $this->date);
        Utility::ensureModelExists($defectiveProduction);

        return $payloadData;
    }

    /**
     * CHANGEOVER状態の更新処理を実行する。
     *
     * @param ProductionHistory $history
     * @param ProductionHistoryRepository $productionHistoryRepository
     * @return void
     */
    private function handleChangeoverState(
        ProductionHistory $history,
        ProductionHistoryRepository $productionHistoryRepository,
    ): void {
        Log::info('Dispatch Sync FinishChangeoverJob after commit');
        $productionHistoryRepository->updateStatus($history, ProductionStatus::RUNNING());

        DB::afterCommit(function (): void {
            FinishChangeoverJob::dispatchSync($this->productionLineId, $this->count, $this->date);
        });
    }

    /**
     * BREAKDOWN状態の更新処理を実行する。
     *
     * @param ProductionLine $productionLine
     * @param ProductionHistory $history
     * @param ProductionHistoryRepository $productionHistoryRepository
     * @return void
     */
    private function handleBreakdownState(
        ProductionLine $productionLine,
        ProductionHistory $history,
        ProductionHistoryRepository $productionHistoryRepository,
    ): void {
        if (!$productionLine->indicator) {
            return;
        }

        Log::info('Dispatch Sync FinishBreakdownJob after commit');
        $productionHistoryRepository->updateStatus($history, ProductionStatus::RUNNING());

        DB::afterCommit(function (): void {
            FinishBreakdownJob::dispatchSync($this->productionLineId, $this->count, $this->date);
        });
    }
}
