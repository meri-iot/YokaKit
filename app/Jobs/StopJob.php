<?php

namespace App\Jobs;

use App\Data\PayloadData;
use App\Events\ProductionSummaryNotification;
use App\Repositories\PayloadRepository;
use App\Repositories\ProductionHistoryRepository;
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
 * 生産停止ジョブ
 */
class StopJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * 生産停止ジョブのインスタンスを作成する。
     *
     * @return void
     */
    public function __construct(
        private readonly int $productionHistoryId,
        private readonly Carbon $date,
        private readonly bool $isDispatchEvent,
    ) {
        Log::info('Dispatch Stop Job', [
            'date' => Utility::format($this->date),
            'productionHistoryId' => $this->productionHistoryId,
        ]);
    }

    /**
     * 生産停止ジョブを実行する。
     *
     * @return void
     */
    public function handle()
    {
        Log::info('Execute Stop Job', [
            'date' => Utility::format($this->date),
            'productionHistoryId' => $this->productionHistoryId,
        ]);
        DB::transaction(function () {

            /** @var ProductionHistoryRepository */
            $productionHistoryRepository = App::make(ProductionHistoryRepository::class);
            /** @var PayloadRepository */
            $payloadRepository = App::make(PayloadRepository::class);

            $history = $productionHistoryRepository->find($this->productionHistoryId, ['productionLines']);
            Utility::throwIfNullException($history);

            foreach ($history->productionLines as $productionLine) {
                $payloadData = $payloadRepository->updatePayload(
                    $productionLine,
                    fn(PayloadData $x) => $x->complete($this->date)
                );
                // $result = $payloadRepository->delete($productionLine->production_line_id);
                // Utility::throwIfException($productionLine, $result);
                if ($productionLine->indicator === true && $this->isDispatchEvent === true) {
                    ProductionSummaryNotification::dispatch($history, $payloadData);
                }
            }
        });
    }
}
