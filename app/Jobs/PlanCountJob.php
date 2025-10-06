<?php

namespace App\Jobs;

use App\Data\PayloadData;
use App\Events\ProductionSummaryNotification;
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

class PlanCountJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     *
     * @param integer $productionLineId
     * @param integer $cycleTimeMs
     * @param Carbon $planDate
     * @param boolean $isChangeover
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
     * @return void
     */
    public function handle()
    {
        Log::debug('Execute PlanCountJob', [
            'planDate' => Utility::format($this->planDate),
            'productionLineId' => $this->productionLineId,
            'cycleTime' => $this->cycleTimeMs,
            'isChangeover' => $this->isChangeover,
            'jobKey' => $this->jobKey,
        ]);

        DB::transaction(function () {

            /** @var ProductionLineRepository */
            $productionLineRepository = App::make(ProductionLineRepository::class);
            /** @var PayloadRepository */
            $payloadRepository = App::make(PayloadRepository::class);
            /** @var ProductionRepository */
            $productionRepository = App::make(ProductionRepository::class);

            // 指定した生産ラインIDを取得してなかったら終了
            $productionLine = $productionLineRepository->find($this->productionLineId, ['productionHistory', 'payload']);
            Utility::throwIfNullException($productionLine);

            // 計画値ジョブキーを取得
            $payload = $productionLine->payload;
            $payloadData = $payload->getPayloadData();
            $jobKey = $payloadData->jobKey;

            // 生産履歴
            $history = $productionLine->productionHistory;

            if ($jobKey === $this->jobKey && !$payloadData->isComplete) {

                // ペイロードを更新
                $payloadData = $payloadRepository->updatePayload(
                    $payload,
                    fn(PayloadData $x) => $x->update($this->planDate)
                );

                // ペイロードを書き直す
                $production = $productionRepository->save($productionLine->production_line_id, $payloadData);
                Utility::throwIfNullException($production);

                // 指標となるラインの場合は通知
                $productionLine->indicator && ProductionSummaryNotification::dispatch($history, $payloadData);

                // 次の計画値カウントアップタイミングを取得
                $delay = $this->planDate->copy()->addMilliseconds($this->nextPlanCountDelay($payloadData));
                // 計画値カウントジョブを登録
                PlanCountJob::dispatch($productionLine->production_line_id, $this->cycleTimeMs, $delay, $this->isChangeover, $this->jobKey)->delay($delay);
            }
        });
    }

    private function nextPlanCountDelay(PayloadData $payloadData): int
    {
        if ($this->isChangeover) {
            return $this->cycleTimeMs;
        } else {
            return $this->cycleTimeMs - ($payloadData->operatingTime % $this->cycleTimeMs);
        }
    }
}
