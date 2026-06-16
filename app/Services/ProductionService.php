<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ProductionStatus;
use App\Exceptions\ProductionException;
use App\Jobs\CountJob;
use App\Jobs\FinishBreakdownJob;
use App\Jobs\FinishChangeoverJob;
use App\Models\ProductionLine;
use App\Repositories\ProductionHistoryRepository;
use App\Repositories\ProductionLineRepository;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 生産データサービス
 */
class ProductionService
{
    /**
     * コンストラクタ
     */
    public function __construct(
        private readonly ProductionHistoryRepository $productionHistory,
        private readonly ProductionLineRepository $productionLine,
    ) {}

    /**
     * 生産数のカウント情報を登録する
     *
     * @param string $ipAddress IPアドレス
     * @param int $count カウント
     * @param int|string $pinNumber ピン番号
     * @param Carbon $datetime 時刻
     * @return void
     * @throws ProductionException 生産通知エラー
     */
    public function store(string $ipAddress, int $count, int|string $pinNumber, Carbon $datetime): void
    {
        DB::transaction(function () use ($ipAddress, $count, $pinNumber, $datetime) {

            // 生産ラインを検索
            $productionLine = $this->getRunningProductionLine($ipAddress, $pinNumber);

            // 生産ライン情報を更新
            $offsetCount = $this->productionLine->updateLineInfo($productionLine, $count);

            // 実際のカウント
            $adjustedCount = $count - $offsetCount;

            $history = $productionLine->productionHistory;
            $productionLineId = $productionLine->production_line_id;

            switch ($history->status) {
                case ProductionStatus::RUNNING():
                    // コミット後にジョブを投入し、ロールバック時の誤通知を防ぐ
                    DB::afterCommit(fn() => CountJob::dispatch($productionLineId, $adjustedCount, $datetime));
                    break;

                case ProductionStatus::CHANGEOVER():
                    // ステータスを稼働中にする
                    $this->productionHistory->updateStatus($history, ProductionStatus::RUNNING());
                    DB::afterCommit(fn() => FinishChangeoverJob::dispatch($productionLineId, $adjustedCount, $datetime));
                    break;

                case ProductionStatus::BREAKDOWN():
                    DB::afterCommit(fn() => FinishBreakdownJob::dispatch($productionLineId, $adjustedCount, $datetime));
                    break;
                default:
                    break;
            }
        });
    }

    /**
     * 生産中のラインを取得する
     *
     * @param string $ipAddress IPアドレス
     * @param int|string $pinNumber ピン番号
     * @return ProductionLine 生産中のライン
     * @throws ProductionException 生産通知エラー
     */
    private function getRunningProductionLine(string $ipAddress, int|string $pinNumber): ProductionLine
    {
        // 生産ラインを検索
        $productionLine = $this->productionLine->lastProductionLine($ipAddress, $pinNumber);

        // 生産ラインがない場合は終了
        if (is_null($productionLine)) {
            Log::debug('Production line is not found.', [
                'ip_address' => $ipAddress,
                'pin_number' => $pinNumber,
            ]);
            throw new ProductionException();
        }

        // 生産が停止していたら終了
        $history = $productionLine->productionHistory;
        if ($history->isComplete()) {
            // Log::debug('Process is not RUNNING.');
            throw new ProductionException();
        }

        return $productionLine;
    }
}
