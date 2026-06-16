<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\PayloadData;
use App\Data\ProductionSummaryData;
use App\Enums\ProductionStatus;
use App\Models\CycleTime;
use App\Models\Process;
use App\Models\ProductionHistory;
use App\Services\Utility;
use Carbon\Exceptions\InvalidFormatException;
use Illuminate\Pagination\LengthAwarePaginator;
use InvalidArgumentException;
use Illuminate\Support\Carbon;

/**
 * 生産履歴リポジトリ
 *
 * @extends AbstractRepository<ProductionHistory>
 */
class ProductionHistoryRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string<ProductionHistory>
     */
    public function model(): string
    {
        return ProductionHistory::class;
    }

    /**
     * 稼働履歴の生産ステータスを更新する
     *
     * @param ProductionHistory $history 対象の稼働履歴
     * @param ProductionStatus $status ステータス
     * @return void
     */
    public function updateStatus(ProductionHistory $history, ProductionStatus $status): void
    {
        $result = $this->updateModel($history, ['status' => $status]);
        Utility::ensureOperationSucceeded($history, $result);
    }

    /**
     * 稼働履歴を登録する
     *
     * @param Process $process 工程
     * @param CycleTime $cycleTime サイクルタイム
     * @param ProductionStatus $status ステータス
     * @param int|null $goal 目標値
     * @return ProductionHistory|null 稼働履歴
     */
    public function storeHistory(Process $process, CycleTime $cycleTime, ProductionStatus $status, ?int $goal = null): ?ProductionHistory
    {
        $productionHistory = new ProductionHistory([
            'process_id' => $process->process_id,
            'part_number_id' => $cycleTime->part_number_id,
            'process_name' => $process->process_name,
            'plan_color' => $process->plan_color,
            'count_switch' => $process->count_switch,
            'part_number_name' => $cycleTime->partNumber->part_number_name,
            'cycle_time' => $cycleTime->cycle_time,
            'over_time' => $cycleTime->over_time,
            'goal' => $goal,
            'start' => Utility::now(),
            'status' => $status,
        ]);
        return $this->storeModel($productionHistory) ? $productionHistory : null;
    }

    /**
     * 稼働を停止する
     *
     * @param ProductionHistory $history 稼働履歴
     * @param Carbon $date 停止時刻
     * @return bool 成否
     */
    public function stop(ProductionHistory $history, Carbon $date): bool
    {
        return $this->updateModel($history, [
            'stop' => $date,
            'status' => ProductionStatus::COMPLETE(),
        ]);
    }

    /**
     * 指定した工程IDの稼働履歴を取得する
     *
     * 日付条件が未指定または不正な場合は、当日を含む直近 7 日分を対象にする。
     * `Utility::parse()` は null / 空文字で例外を投げるため、ここで入力値を検証し、
     * 不正値はフォールバック条件へ切り替える。
     *
     * @param int $processId 工程ID
     * @param string|null $partNumberName 品番名
     * @param string|null $startDate 開始日
     * @param string|null $endDate 終了日
     * @param int $page ページあたりの件数
     * @return LengthAwarePaginator<ProductionHistory>
     */
    public function histories(int $processId, ?string $partNumberName, ?string $startDate, ?string $endDate, int $page): LengthAwarePaginator
    {
        $now = Utility::now();
        $start = $now->copy()->subDays(6)->startOfDay();
        $end = $now->copy()->endOfDay();

        if (!is_null($startDate) && $startDate !== '' && !is_null($endDate) && $endDate !== '') {
            try {
                $start = Utility::parse($startDate, 'Y-m-d')->startOfDay();
                $end = Utility::parse($endDate, 'Y-m-d')->endOfDay();
            } catch (InvalidArgumentException | InvalidFormatException) {
                $start = $now->copy()->subDays(6)->startOfDay();
                $end = $now->copy()->endOfDay();
            }
        }

        $query = $this->model->where('process_id', $processId);
        if ($partNumberName) {
            $query = $query->where('part_number_name', $partNumberName);
        }

        return $query
            ->where('stop', '>=', $start)
            ->where('start', '<=', $end)
            ->orderBy('start', 'desc')
            ->with(['indicatorLine', 'indicatorLine.payload'])
            ->paginate($page);
    }

    /**
     * 指定した工程の生産履歴をソフトデリートする
     *
     * @param array<int,int|string> $productionHistoryIds 生産履歴IDの配列
     * @return int 削除件数
     */
    public function softDelete(array $productionHistoryIds): int
    {
        return $this->model->whereIn('production_history_id', $productionHistoryIds)->delete();
    }

    /**
     * 生産サマリー通知データを生成する。
     *
     * @param ProductionHistory $history 稼働履歴
     * @param PayloadData $payloadData 指標計算データ
     * @return ProductionSummaryData
     */
    public function makeProductionSummary(ProductionHistory $history, PayloadData $payloadData): ProductionSummaryData
    {
        return new ProductionSummaryData(
            productionHistoryId: $history->production_history_id,
            processId: $history->process_id,
            processName: $history->process_name,
            partNumberId: $history->part_number_id,
            partNumberName: $history->part_number_name,
            goal: $history->goal,
            start: Utility::format($history->start),
            countSwitch: $payloadData->countSwitch,
            statusName: $payloadData->status()->key,
            breakdownCount: count($payloadData->breakdowns),
            inPlannedOutage: $payloadData->inPlannedOutage(),
            count: $payloadData->count,
            lineId: $payloadData->lineId,
            defectiveCounts: $payloadData->defectiveCounts,
            at: $payloadData->at,
            isComplete: $payloadData->isComplete,
            workingTime: $payloadData->workingTime,
            loadingTime: $payloadData->loadingTime,
            operatingTime: $payloadData->operatingTime,
            netTime: $payloadData->netTime,
            autoResumeCount: $payloadData->autoResumeCount,
            breakdowns: $payloadData->breakdowns,
            cycleTimeMs: $payloadData->cycleTimeMs,
            overTimeMs: $payloadData->overTimeMs,
            plannedOutages: $payloadData->plannedOutages,
            changeovers: $payloadData->changeovers,
            indicator: $payloadData->indicator,
        );
    }

    /**
     * 指標API向けのサマリー配列を組み立てる。
     *
     * @param PayloadData $payloadData 指標計算データ
     * @return array<string,mixed>
     */
    public function makeIndicatorSummary(PayloadData $payloadData): array
    {
        $data = $payloadData->toArray();
        $data['totalCount'] = $payloadData->totalCount();
        $data['goodCount'] = $payloadData->goodCount();
        $data['defectiveCount'] = $payloadData->defectiveCount();
        $data['goodRate'] = $payloadData->goodRate();
        $data['defectiveRate'] = $payloadData->defectiveRate();
        $data['timeOperatingRate'] = $payloadData->timeOperatingRate();
        $data['performanceOperatingRate'] = $payloadData->performanceOperatingRate();
        $data['overallEquipmentEffectiveness'] = $payloadData->overallEquipmentEffectiveness();
        $data['planCount'] = $payloadData->planCount();
        $data['achievementRate'] = $payloadData->achievementRate();
        $data['cycleTime'] = $payloadData->cycleTime();
        $data['status'] = $payloadData->status();
        unset($data['lineId']);
        unset($data['defectiveCounts']);
        unset($data['countSwitch']);
        unset($data['indicator']);
        unset($data['jobKey']);
        return $data;
    }
}
