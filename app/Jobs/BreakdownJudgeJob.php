<?php

namespace App\Jobs;

use App\Data\PayloadData;
use App\Enums\ProductionStatus;
use App\Events\ProductionSummaryNotification;
use App\Models\Production;
use App\Models\ProductionLine;
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
 * チョコ停判定ジョブクラス
 */
class BreakdownJudgeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * チョコ停判定ジョブのインスタンスを作成する。
     *
     * @param Production $production チョコ停基準となる生産データ
     * @param Carbon $breakdownTime チョコ停発生予定時刻
     */
    public function __construct(
        private readonly Production $production,
        private readonly Carbon $breakdownTime,
    ) {
        Log::info('Dispatch BreakdownJudgeJob', [
            'breakdownTime' => Utility::format($this->breakdownTime),
            'production' => $this->production->toArray(),
        ]);
    }

    /**
     * チョコ停判定ジョブを実行する。
     *
     * @return void
     */
    public function handle()
    {
        Log::info('Execute BreakdownJudgeJob', [
            'breakdownTime' => Utility::format($this->breakdownTime),
            'production' => $this->production->toArray(),
        ]);

        DB::transaction(function () {

            /** @var PayloadRepository */
            $payloadRepository = App::make(PayloadRepository::class);
            /** @var ProductionHistoryRepository */
            $productionHistoryRepository = App::make(ProductionHistoryRepository::class);
            /** @var ProductionLineRepository */
            $productionLineRepository = App::make(ProductionLineRepository::class);
            /** @var ProductionRepository */
            $productionRepository = App::make(ProductionRepository::class);

            /** @var ?ProductionLine */
            $productionLine = $productionLineRepository->find($this->production->production_line_id, ['productionHistory.productionLines']);
            Utility::throwIfNullException($productionLine);

            $payload = $payloadRepository->getPayload($productionLine);
            $payloadData = $payload->getPayloadData();

            if ($payloadData->status()->isNot(ProductionStatus::RUNNING())) {
                // 稼働中でない場合は終了
                Log::debug('Status is not RUNNING.');
                return;
            }

            // 生産時刻からチョコ停時刻からチョコ停が発生したかどうかをチェック
            $isBreakdown = $productionRepository->judgeBreakdown($this->production, $this->breakdownTime);
            if (!$isBreakdown) {
                // チョコ停ではなかった場合は終了
                Log::info('Breakdown is not occurred.');
                return;
            }

            // 生産履歴
            $history = $productionLine->productionHistory;
            if ($history->status->is(ProductionStatus::RUNNING())) {
                Log::info('Update Status as BREAKDOWN.');
                $productionHistoryRepository->updateStatus($history, ProductionStatus::BREAKDOWN());
            }

            // チョコ停の開始を生産データに追加
            $productionLines = $productionLine->productionHistory->productionLines;
            foreach ($productionLines as $pl) {
                $payloadData = $payloadRepository->updatePayload(
                    $pl,
                    fn(PayloadData $x) => $x->addBreakdown($this->breakdownTime, true)
                );
                $productionRepository->save($pl->production_line_id, $payloadData);
                $pl->indicator && ProductionSummaryNotification::dispatch($history, $payloadData);
            }
        });
    }

    /**
     * チョコ停判定ジョブを登録し、指定されたオーバータイムだけ遅延実行させる。
     *
     * @param integer $overTimeMs オーバータイム[ms]
     * @param Production $production チョコ停基準となる生産データ
     * @return void
     */
    public static function delayedDispatch(int $overTimeMs, Production $production): void
    {
        $delay = $production->at->copy()->addMilliseconds($overTimeMs);
        BreakdownJudgeJob::dispatch($production, $delay)->delay($delay);
    }
}
