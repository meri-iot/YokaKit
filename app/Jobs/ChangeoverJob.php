<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Data\PayloadData;
use App\Events\ProductionSummaryNotification;
use App\Repositories\PayloadRepository;
use App\Repositories\ProductionHistoryRepository;
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
 * 段取り替えの開始/終了ジョブ
 */
class ChangeoverJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 段取り替えの開始/終了ジョブインスタンスを作成する
     *
     * @param int $productionHistoryId 生産履歴ID
     * @param Carbon $date 段取り替え開始/終了の時刻
     * @param bool $changeover trueの場合段取り替えの開始
     */
    public function __construct(
        private readonly int $productionHistoryId,
        private readonly Carbon $date,
        private readonly bool $changeover,
    ) {
        Log::info('Dispatch ChangeoverJob', [
            'date' => Utility::format($this->date),
            'productionHistoryId' => ($this->productionHistoryId),
            'changeover' => $this->changeover,
        ]);
    }

    /**
     * 段取り替えの開始/終了ジョブを実行する。
     *
     * Queue Worker が handle 引数へ依存解決した Repository を注入する。
     *
     * @param PayloadRepository $payloadRepository
     * @param ProductionHistoryRepository $productionHistoryRepository
     * @param ProductionRepository $productionRepository
     * @return void
     */
    public function handle(
        PayloadRepository $payloadRepository,
        ProductionHistoryRepository $productionHistoryRepository,
        ProductionRepository $productionRepository,
    ): void {
        Log::info('Execute ChangeoverJob', [
            'date' => Utility::format($this->date),
            'productionHistoryId' => ($this->productionHistoryId),
            'changeover' => $this->changeover,
        ]);
        DB::transaction(function () use (
            $payloadRepository,
            $productionHistoryRepository,
            $productionRepository,
        ) {

            $history = $productionHistoryRepository->find($this->productionHistoryId, ['productionLines.payload']);
            Utility::ensureModelExists($history);

            $cycleTimeMs = $history->cycleTimeMs();
            foreach ($history->productionLines as $productionLine) {

                $productionLineId = $productionLine->production_line_id;

                // ペイロードを更新
                $payloadData = $payloadRepository->updatePayload($productionLine, function (PayloadData $x) {
                    // 段取り替えの開始/終了区間を追加
                    $x->addChangeover($this->date, $this->changeover);
                    // ジョブキーの更新
                    $x->jobKey = (string)Str::uuid();
                });

                // 生産データを追加
                $production = $productionRepository->save($productionLineId, $payloadData);
                Utility::ensureModelExists($production);

                // 通知・後続ジョブはコミット後に投入し、未コミット状態を参照しないようにする。
                if ($productionLine->indicator) {
                    DB::afterCommit(static function () use ($productionHistoryRepository, $history, $payloadData): void {
                        ProductionSummaryNotification::dispatch($productionHistoryRepository->makeProductionSummary($history, $payloadData));
                    });
                }

                // 計画値カウントジョブを登録
                $delay = $this->date->copy()->addMilliseconds($cycleTimeMs - ($payloadData->operatingTime % $cycleTimeMs));
                PlanCountJob::dispatch($productionLineId, $cycleTimeMs, $delay, $this->changeover, $payloadData->jobKey)
                    ->delay($delay)
                    ->afterCommit();

                // チョコ停判定ジョブを登録
                if ($productionLine->indicator === true && $this->changeover === false) {
                    DB::afterCommit(function () use ($history, $production): void {
                        BreakdownJudgeJob::delayedDispatch($history->overTimeMs(), $production);
                    });
                }
            }
        });
    }
}
