<?php

namespace App\Jobs;

use App\Data\PayloadData;
use App\Events\ProductionSummaryNotification;
use App\Repositories\DefectiveProductionRepository;
use App\Repositories\PayloadRepository;
use App\Repositories\ProductionLineRepository;
use App\Repositories\ProductionRepository;
use App\Services\Utility;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\App;
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
     * @return void
     */
    public function handle()
    {
        Log::info('Execute FinishBreakdownJob', [
            'date' => Utility::format($this->date),
            'productionLineId' => $this->productionLineId,
            'count' => $this->count,
        ]);
        DB::transaction(function () {

            /** @var PayloadRepository */
            $payloadRepository = App::make(PayloadRepository::class);
            /** @var ProductionLineRepository */
            $productionLineRepository = App::make(ProductionLineRepository::class);
            /** @var ProductionRepository */
            $productionRepository = App::make(ProductionRepository::class);

            // 関連する生産ラインを検索
            $productionLine = $productionLineRepository->find($this->productionLineId, ['productionHistory.productionLines']);
            Utility::throwIfNullException($productionLine);

            // 関連する生産履歴
            $history = $productionLine->productionHistory;

            foreach ($history->productionLines as $line) {

                $payloadData = $payloadRepository->updatePayload($line, function (PayloadData $x) use ($productionLine, $line) {
                    // カウントアップ
                    if ($productionLine->defective === false) {
                        if ($this->productionLineId === $line->production_line_id) {
                            $x->count = $this->count;
                        }
                    } else {
                        if ($productionLine->parent_id === $line->production_line_id) {
                            $x->setDefectiveCount($this->productionLineId, $this->count);
                            $this->storeDefectiveProduction();
                        }
                    }
                    // チョコ停の終了時刻を追加
                    $x->addBreakdown($this->date, false);
                });

                // 生産データレコードを追加
                $production = $productionRepository->save($line->production_line_id, $payloadData);
                Utility::throwIfNullException($production);

                // ブロードキャスト通知
                ProductionSummaryNotification::dispatch($history, $payloadData);

                // チョコ停ジョブを登録
                BreakdownJudgeJob::delayedDispatch($history->overTimeMs(), $production);
            }
        });
    }

    private function storeDefectiveProduction(): void
    {
        /** @var DefectiveProductionRepository */
        $defectiveProductionRepository = App::make(DefectiveProductionRepository::class);
        $defectiveProduction = $defectiveProductionRepository->save($this->productionLineId, $this->count, $this->date);
        Utility::throwIfNullException($defectiveProduction);
    }
}
