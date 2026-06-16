<?php

declare(strict_types=1);

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
     * Queue Worker が handle 引数へ依存解決した Repository を注入する。
     *
     * @param PayloadRepository $payloadRepository
     * @param ProductionHistoryRepository $productionHistoryRepository
     * @param ProductionLineRepository $productionLineRepository
     * @param ProductionRepository $productionRepository
     * @return void
     */
    public function handle(
        PayloadRepository $payloadRepository,
        ProductionHistoryRepository $productionHistoryRepository,
        ProductionLineRepository $productionLineRepository,
        ProductionRepository $productionRepository,
    ): void {
        Log::info('Execute BreakdownJudgeJob', [
            'breakdownTime' => Utility::format($this->breakdownTime),
            'production' => $this->production->toArray(),
        ]);

        DB::transaction(function () use (
            $payloadRepository,
            $productionHistoryRepository,
            $productionLineRepository,
            $productionRepository,
        ) {

            // 判定対象の生産ラインと現在の指標データを取得する。

            /** @var ?ProductionLine */
            $productionLine = $productionLineRepository->find($this->production->production_line_id, ['productionHistory.productionLines']);
            Utility::ensureModelExists($productionLine);

            $payload = $payloadRepository->getPayload($productionLine);
            $payloadData = $payload->getPayloadData();

            if ($payloadData->status()->isNot(ProductionStatus::RUNNING())) {
                // 稼働中でない場合はチョコ停判定対象外。
                Log::debug('Status is not RUNNING.');
                return;
            }

            if ($payloadData->indicator === false) {
                // 指標となるラインでない場合は判定を行わない。
                Log::debug('Indicator is false.');
                return;
            }

            // 基準生産から判定時刻までに増産やステータス変化がないかを調べる。
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

            // 指標データへチョコ停開始を記録し、スナップショット生産データを保存して通知する。
            $indicatorLine = $productionLine->productionHistory->indicatorLine;
            $payloadData = $payloadRepository->updatePayload(
                $indicatorLine,
                fn(PayloadData $x) => $x->addBreakdown($this->breakdownTime, true)
            );
            $productionRepository->save($indicatorLine->production_line_id, $payloadData);
            DB::afterCommit(static function () use ($productionHistoryRepository, $history, $payloadData): void {
                ProductionSummaryNotification::dispatch($productionHistoryRepository->makeProductionSummary($history, $payloadData));
            });
        });
    }

    /**
     * チョコ停判定ジョブを登録し、指定されたオーバータイムだけ遅延実行させる。
     *
     * @param int $overTimeMs オーバータイム[ms]
     * @param Production $production チョコ停基準となる生産データ
     * @return void
     */
    public static function delayedDispatch(int $overTimeMs, Production $production): void
    {
        $delay = $production->at->copy()->addMilliseconds($overTimeMs);
        BreakdownJudgeJob::dispatch($production, $delay)
            ->delay($delay)
            ->afterCommit();
    }
}
