<?php

declare(strict_types=1);

namespace App\Data;

use Spatie\LaravelData\Data;

/**
 * 生産サマリー通知データ
 *
 * ProductionSummaryNotification のブロードキャスト引数を型付けするための値オブジェクト。
 * ProductionHistory::makeProductionSummary() のファクトリーメソッド経由でのみ生成される。
 */
final class ProductionSummaryData extends Data
{
    /**
     * @param int $productionHistoryId 生産履歴ID
     * @param int $processId 工程ID
     * @param string $processName 工程名
     * @param int $partNumberId 品番ID
     * @param string $partNumberName 品番名
     * @param int|null $goal 目標値
     * @param string $start 生産開始時刻（フォーマッド済み）
     * @param bool $countSwitch カウント切替
     * @param string $statusName 生産ステータス名
     * @param int $breakdownCount チョコ停回数
     * @param bool $inPlannedOutage 計画停止中フラグ
     * @param int $count 生産数
     * @param int $lineId 生産ラインID
     * @param array<int,int> $defectiveCounts 不良品カウント
     * @param string $at 現在時刻
     * @param bool $isComplete 終了フラグ
     * @param int $workingTime 操業時間[秒]
     * @param int $loadingTime 負荷時間[秒]
     * @param int $operatingTime 稼働時間[秒]
     * @param int $netTime 正味稼働時間[秒]
     * @param int $autoResumeCount 自動復帰回数
     * @param array<int, array{from: string, to: string|null}> $breakdowns チョコ停区間
     * @param int $cycleTimeMs サイクルタイム[ms]
     * @param int $overTimeMs オーバータイム[ms]
     * @param array<int, array{startTime: string, endTime: string}> $plannedOutages 計画停止時間
     * @param array<int, array{from: string, to: string|null}> $changeovers 段取り替え区間
     * @param bool $indicator 指標フラグ
     */
    public function __construct(
        public readonly int $productionHistoryId,
        public readonly int $processId,
        public readonly string $processName,
        public readonly int $partNumberId,
        public readonly string $partNumberName,
        public readonly ?int $goal,
        public readonly string $start,
        public readonly bool $countSwitch,
        public readonly string $statusName,
        public readonly int $breakdownCount,
        public readonly bool $inPlannedOutage,
        public readonly int $count,
        public readonly int $lineId,
        public readonly array $defectiveCounts,
        public readonly string $at,
        public readonly bool $isComplete,
        public readonly int $workingTime,
        public readonly int $loadingTime,
        public readonly int $operatingTime,
        public readonly int $netTime,
        public readonly int $autoResumeCount,
        public readonly array $breakdowns,
        public readonly int $cycleTimeMs,
        public readonly int $overTimeMs,
        public readonly array $plannedOutages,
        public readonly array $changeovers,
        public readonly bool $indicator,
    ) {}
}
