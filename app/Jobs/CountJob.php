<?php

namespace App\Jobs;

use App\Data\PayloadData;
use App\Enums\ProductionStatus;
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
use Illuminate\Support\Facades\App;
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
     * @return void
     */
    public function handle()
    {
        Log::info('Execute CountJob', [
            'date' => Utility::format($this->date),
            'productionLineId' => $this->productionLineId,
            'count' => $this->count,
        ]);
        DB::transaction(function () {

            /** @var ProductionLineRepository */
            $productionLineRepository = App::make(ProductionLineRepository::class);
            /** @var ProductionHistoryRepository */
            $productionHistoryRepository = App::make(ProductionHistoryRepository::class);
            /** @var PayloadRepository */
            $payloadRepository = App::make(PayloadRepository::class);
            /** @var ProductionRepository */
            $productionRepository = App::make(ProductionRepository::class);
            /** @var DefectiveProductionRepository */
            $defectiveProductionRepository = App::make(DefectiveProductionRepository::class);

            // 生産ラインデータを取得
            $productionLine = $productionLineRepository->find($this->productionLineId, ['productionHistory']);
            Utility::throwIfNullException($productionLine);

            // ペイロードとデータを取得
            $payload = $payloadRepository->getPayload($productionLine);
            $payloadData = $payload->getPayloadData();

            $history = $productionLine->productionHistory;

            switch ($payloadData->status()) {

                case ProductionStatus::RUNNING():

                    if ($productionLine->defective === false) {
                        // ペイロードのカウント値を更新
                        $payloadData = $payloadRepository->updatePayload(
                            $payload,
                            function (PayloadData $x) {
                                $x->count = $this->count;
                                $x->update($this->date);
                            }
                        );
                    } else {
                        // ペイロードの不良品カウント値を更新
                        $payloadData = $payloadRepository->updatePayload(
                            $payload,
                            function (PayloadData $x) {
                                $x->setDefectiveCount($this->productionLineId, $this->count);
                                $x->update($this->date);
                            }
                        );
                        // 不良品データを追加
                        $defectiveProduction = $defectiveProductionRepository->save($this->productionLineId, $this->count, $this->date);
                        Utility::throwIfNullException($defectiveProduction);
                    }

                    // 生産データを追加
                    $production = $productionRepository->save($this->productionLineId, $payloadData);
                    Utility::throwIfNullException($production);

                    // チョコ停判定ジョブを予約
                    $productionLine->defective || BreakdownJudgeJob::delayedDispatch($history->overTimeMs(), $production);

                    // 通知
                    ProductionSummaryNotification::dispatch($history, $payloadData);
                    break;
                case ProductionStatus::CHANGEOVER():
                    // 段取り替え終了処理を即時実行
                    Log::info('Dispatch Sync FinishChangeoverJob');
                    $productionHistoryRepository->updateStatus($history, ProductionStatus::RUNNING());
                    FinishChangeoverJob::dispatchSync($this->productionLineId, $this->count, $this->date);
                    break;
                case ProductionStatus::BREAKDOWN():
                    // チョコ停終了処理を即時実行
                    Log::info('Dispatch Sync FinishBreakdownJob');
                    $productionHistoryRepository->updateStatus($history, ProductionStatus::RUNNING());
                    FinishBreakdownJob::dispatchSync($this->productionLineId, $this->count, $this->date);
                    break;
                default:
                    break;
            }
        });
    }
}
