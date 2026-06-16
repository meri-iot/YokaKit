<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Data\PayloadData;
use App\Events\ProductionSummaryNotification;
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
use Illuminate\Support\Str;

/**
 * 段取り替えの自動終了ジョブ
 */
class FinishChangeoverJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 段取り替えの自動終了ジョブのインスタンスを作成する。
     *
     * @param int $productionLineId 生産ラインID
     * @param int $count カウント
     * @param Carbon $date 時刻
     */
    public function __construct(
        private readonly int $productionLineId,
        private readonly int $count,
        private readonly Carbon $date,
    ) {
        Log::info('Dispatch FinishChangeoverJob', [
            'date' => Utility::format($this->date),
            'productionLineId' => $this->productionLineId,
            'count' => $this->count,
        ]);
    }

    /**
     * 段取り替えの自動終了ジョブを実行する。
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
        Log::info('Execute FinishChangeoverJob', [
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

            $history = $productionLine->productionHistory;
            $cycleTimeMs = $history->cycleTimeMs();

            foreach ($history->productionLines as $line) {

                $payloadData = $payloadRepository->updatePayload($line, function (PayloadData $x) use ($productionLine, $line, $defectiveProductionRepository) {
                    if ($productionLine->defective === false) {
                        if ($this->productionLineId === $line->production_line_id) {
                            // 生産数をカウントアップ
                            $x->count = $this->count;
                            // 段取り替え自動復帰カウントをインクリメント
                            $x->autoResumeCount++;
                        }
                    } else {
                        if ($productionLine->parent_id === $line->production_line_id) {
                            // 不良品数をカウントアップ
                            $x->setDefectiveCount($this->productionLineId, $this->count);
                            $this->storeDefectiveProduction($defectiveProductionRepository);
                            // 段取り替え自動復帰カウントをインクリメント
                            $x->autoResumeCount++;
                        }
                    }
                    // 段取り替えの終了時刻を追加
                    $x->addChangeover($this->date, false);
                    // ジョブキーを更新
                    $x->jobKey = (string) Str::uuid();
                });

                // 生産データレコードを追加
                $production = $productionRepository->save($line->production_line_id, $payloadData);
                Utility::ensureModelExists($production);

                // 次の計画値ジョブを登録
                $delay = $this->date->copy()->addMilliseconds($cycleTimeMs - ($payloadData->operatingTime % $cycleTimeMs));
                PlanCountJob::dispatch($line->production_line_id, $cycleTimeMs, $delay, false, $payloadData->jobKey)
                    ->delay($delay)
                    ->afterCommit();

                if ($line->indicator === true) {
                    // 指標対象の場合はブロードキャスト通知
                    DB::afterCommit(static function () use ($productionHistoryRepository, $history, $payloadData): void {
                        ProductionSummaryNotification::dispatch($productionHistoryRepository->makeProductionSummary($history, $payloadData));
                    });
                    // 指標となるラインの場合にチョコ停ジョブを登録
                    DB::afterCommit(function () use ($history, $production): void {
                        BreakdownJudgeJob::delayedDispatch($history->overTimeMs(), $production);
                    });
                }
            }
        });
    }

    private function storeDefectiveProduction(DefectiveProductionRepository $defectiveProductionRepository): void
    {
        $defectiveProduction = $defectiveProductionRepository->save($this->productionLineId, $this->count, $this->date);
        Utility::ensureModelExists($defectiveProduction);
    }
}
