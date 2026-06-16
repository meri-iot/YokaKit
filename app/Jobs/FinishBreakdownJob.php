<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Data\PayloadData;
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
 * チョコ停の自動終了ジョブ
 */
class FinishBreakdownJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * チョコ停の自動終了ジョブのインスタンスを作成する。
     *
     * @return void
     */
    public function __construct(
        private readonly int $productionLineId,
        private readonly int $count,
        private readonly Carbon $date,
    ) {
        Log::info('Dispatch FinishBreakdownJob', [
            'date' => Utility::format($this->date),
            'productionLineId' => $this->productionLineId,
            'count' => $this->count,
        ]);
    }

    /**
     * チョコ停の自動終了ジョブを実行する。
     *
     * Queue Worker が handle 引数へ依存解決した Repository を注入する。
     *
     * @param PayloadRepository $payloadRepository
     * @param ProductionLineRepository $productionLineRepository
     * @param ProductionRepository $productionRepository
     * @param DefectiveProductionRepository $defectiveProductionRepository
     * @return void
     */
    public function handle(
        PayloadRepository $payloadRepository,
        ProductionLineRepository $productionLineRepository,
        ProductionRepository $productionRepository,
        DefectiveProductionRepository $defectiveProductionRepository,
        ProductionHistoryRepository $productionHistoryRepository,
    ): void {
        Log::info('Execute FinishBreakdownJob', [
            'date' => Utility::format($this->date),
            'productionLineId' => $this->productionLineId,
            'count' => $this->count,
        ]);
        DB::transaction(function () use (
            $payloadRepository,
            $productionLineRepository,
            $productionRepository,
            $defectiveProductionRepository,
            $productionHistoryRepository,
        ) {
            // 関連する生産ラインを検索
            $productionLine = $productionLineRepository->find($this->productionLineId, ['productionHistory.productionLines']);
            Utility::ensureModelExists($productionLine);

            // 関連する生産履歴
            $history = $productionLine->productionHistory;

            foreach ($history->productionLines as $line) {
                $payloadData = $payloadRepository->updatePayload(
                    $line,
                    function (PayloadData $x) use ($productionLine, $line, $defectiveProductionRepository): void {
                        $this->applyPayloadUpdate($x, $productionLine, $line, $defectiveProductionRepository);
                    }
                );

                // 生産データレコードを追加
                $production = $productionRepository->save($line->production_line_id, $payloadData);
                Utility::ensureModelExists($production);

                $this->dispatchAfterCommit($history, $payloadData, $line, $production, $productionHistoryRepository);
            }
        });
    }

    /**
     * 対象ラインに対するペイロード更新を適用する。
     */
    private function applyPayloadUpdate(
        PayloadData $payloadData,
        ProductionLine $sourceLine,
        ProductionLine $targetLine,
        DefectiveProductionRepository $defectiveProductionRepository,
    ): void {
        if (!$this->isTargetLine($sourceLine, $targetLine)) {
            return;
        }

        if (!$sourceLine->defective) {
            $payloadData->count = $this->count;
        } else {
            $payloadData->setDefectiveCount($this->productionLineId, $this->count);
            $this->storeDefectiveProduction($defectiveProductionRepository);
        }

        if ($targetLine->indicator) {
            // 指標ラインではチョコ停区間の終了時刻を確定する。
            $payloadData->addBreakdown($this->date, false);
        }
    }

    /**
     * 更新元ラインに対して、更新対象ラインかどうかを判定する。
     */
    private function isTargetLine(ProductionLine $sourceLine, ProductionLine $targetLine): bool
    {
        if (!$sourceLine->defective) {
            return $this->productionLineId === $targetLine->production_line_id;
        }

        return $sourceLine->parent_id === $targetLine->production_line_id;
    }

    /**
     * 通知と後続ジョブをコミット後に投入する。
     */
    private function dispatchAfterCommit(
        ProductionHistory $history,
        PayloadData $payloadData,
        ProductionLine $line,
        Production $production,
        ProductionHistoryRepository $productionHistoryRepository,
    ): void {
        DB::afterCommit(static function () use ($productionHistoryRepository, $history, $payloadData): void {
            ProductionSummaryNotification::dispatch($productionHistoryRepository->makeProductionSummary($history, $payloadData));
        });

        if ($line->indicator) {
            DB::afterCommit(function () use ($history, $production): void {
                BreakdownJudgeJob::delayedDispatch($history->overTimeMs(), $production);
            });
        }
    }

    private function storeDefectiveProduction(DefectiveProductionRepository $defectiveProductionRepository): void
    {
        $defectiveProduction = $defectiveProductionRepository->save($this->productionLineId, $this->count, $this->date);
        Utility::ensureModelExists($defectiveProduction);
    }
}
