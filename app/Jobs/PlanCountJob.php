<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Data\PayloadData;
use App\Events\ProductionSummaryNotification;
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

class PlanCountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param int $productionLineId
     * @param int $cycleTimeMs
     * @param Carbon $planDate
     * @param bool $isChangeover
     * @param string $jobKey
     */
    public function __construct(
        private readonly int $productionLineId,
        private readonly int $cycleTimeMs,
        private readonly Carbon $planDate,
        private readonly bool $isChangeover,
        private readonly string $jobKey,
    ) {
        Log::debug('Dispatch PlanCountJob', [
            'planDate' => Utility::format($this->planDate),
            'productionLineId' => $this->productionLineId,
            'cycleTime' => $this->cycleTimeMs,
            'isChangeover' => $this->isChangeover,
            'jobKey' => $this->jobKey,
        ]);
    }

    /**
     * Execute the job.
     *
     * Queue Worker が handle 引数へ依存解決した Repository を注入する。
     *
     * @param ProductionLineRepository $productionLineRepository
     * @param PayloadRepository $payloadRepository
     * @param ProductionRepository $productionRepository
     * @return void
     */
    public function handle(
        ProductionLineRepository $productionLineRepository,
        PayloadRepository $payloadRepository,
        ProductionRepository $productionRepository,
        ProductionHistoryRepository $productionHistoryRepository,
    ): void {
        Log::debug('Execute PlanCountJob', [
            'planDate' => Utility::format($this->planDate),
            'productionLineId' => $this->productionLineId,
            'cycleTime' => $this->cycleTimeMs,
            'isChangeover' => $this->isChangeover,
            'jobKey' => $this->jobKey,
        ]);

        DB::transaction(function () use (
            $productionLineRepository,
            $payloadRepository,
            $productionRepository,
            $productionHistoryRepository,
        ) {

            // 指定した生産ラインが取得できない場合は例外を送出する。
            $productionLine = $productionLineRepository->find($this->productionLineId, ['productionHistory', 'payload']);
            Utility::ensureModelExists($productionLine);

            // 計画値ジョブキーを取得
            $payload = $productionLine->payload;
            $payloadData = $payload->getPayloadData();
            $jobKey = $payloadData->jobKey;

            // 生産履歴
            $history = $productionLine->productionHistory;

            if ($jobKey === $this->jobKey && !$payloadData->isComplete) {
                if ($this->cycleTimeMs <= 0) {
                    Log::warning('Skip PlanCountJob re-dispatch because cycleTimeMs is invalid.', [
                        'productionLineId' => $this->productionLineId,
                        'cycleTimeMs' => $this->cycleTimeMs,
                        'jobKey' => $this->jobKey,
                    ]);
                    return;
                }

                // ペイロードを更新
                $payloadData = $payloadRepository->updatePayload(
                    $payload,
                    fn(PayloadData $x) => $x->update($this->planDate)
                );

                // ペイロードを書き直す
                $production = $productionRepository->save($productionLine->production_line_id, $payloadData);
                Utility::ensureModelExists($production);

                // 通知はコミット後に行い、未コミットデータを参照しないようにする。
                if ($productionLine->indicator) {
                    DB::afterCommit(static function () use ($productionHistoryRepository, $history, $payloadData): void {
                        ProductionSummaryNotification::dispatch($productionHistoryRepository->makeProductionSummary($history, $payloadData));
                    });
                }

                // 次回時刻は計画時刻ベースで算出し、遅延時のみ周期単位で先送りする。
                $delay = $this->nextPlanDate($payloadData, Utility::now());
                // 次の計画値カウントジョブはコミット後に登録する。
                PlanCountJob::dispatch($productionLine->production_line_id, $this->cycleTimeMs, $delay, $this->isChangeover, $this->jobKey)
                    ->delay($delay)
                    ->afterCommit();
            }
        });
    }

    private function nextPlanDate(PayloadData $payloadData, Carbon $now): Carbon
    {
        $next = $this->planDate->copy()->addMilliseconds($this->nextPlanCountDelay($payloadData));
        if ($next->greaterThan($now)) {
            return $next;
        }

        $lagMs = $next->diffInMilliseconds($now);
        $skipTicks = intdiv($lagMs, $this->cycleTimeMs) + 1;

        return $next->addMilliseconds($skipTicks * $this->cycleTimeMs);
    }

    private function nextPlanCountDelay(PayloadData $payloadData): int
    {
        if ($this->isChangeover) {
            return $this->cycleTimeMs;
        } else {
            $remaining = $this->cycleTimeMs - ($payloadData->operatingTime % $this->cycleTimeMs);
            return $remaining === 0 ? $this->cycleTimeMs : $remaining;
        }
    }
}
