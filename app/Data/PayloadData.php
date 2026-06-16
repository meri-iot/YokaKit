<?php

declare(strict_types=1);

namespace App\Data;

use App\Enums\ProductionStatus;
use App\Services\Utility;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Spatie\LaravelData\Data;

/**
 * 指標計算用のデータクラス
 */
class PayloadData extends Data
{
    /**
     * 計画停止時間の区間
     *
     * @var Collection<FromTo>
     */
    private ?Collection $plannedOutageSections = null;

    /**
     * 段取り替えの区間
     *
     * @var Collection<FromTo>
     */
    private ?Collection $changeoverSections = null;

    /**
     * チョコ停の区間
     *
     * @var Collection<FromTo>
     */
    private ?Collection $breakdownSections = null;

    /**
     * 終了フラグ
     *
     * @var boolean
     */
    public bool $isComplete = false;

    /**
     * 生産カウント
     *
     * @var integer
     */
    public int $count = 0;

    /**
     * 現在時刻の文字列
     *
     * @var string
     */
    public string $at;

    /**
     * チョコ停区間
     *
     * @var array<int, array{from: string, to: string|null}>
     */
    public array $breakdowns = [];

    /**
     * 操業時間
     *
     * @var integer
     */
    public int $workingTime = 0;

    /**
     * 負荷時間 (操業時間-休止ロス)
     *
     * @var integer
     */
    public int $loadingTime = 0;

    /**
     * 稼働時間 (負荷時間-停止ロス)
     *
     * @var integer
     */
    public int $operatingTime = 0;

    /**
     * 正味稼働時間 (稼働時間-性能ロス)
     *
     * @var integer
     */
    public int $netTime = 0;

    /**
     * 自動段取り替え復帰回数
     *
     * @var integer
     */
    public int $autoResumeCount = 0;

    /**
     * 計画値ジョブ用のキー
     *
     * @var string
     */
    public string $jobKey;

    /**
     * コンストラクタ
     *
     * @param int $lineId 生産ラインID
     * @param array<int,int> $defectiveCounts 不良品カウント
     * @param string $start 生産開始時刻
     * @param bool $countSwitch カウント切替
     * @param int $cycleTimeMs サイクルタイムミリ秒
     * @param int $overTimeMs オーバータイムミリ秒
     * @param array<int, array{startTime: string, endTime: string}> $plannedOutages 計画停止時間
     * @param array<int, array{from: string, to: string|null}> $changeovers 段取り替え区間
     * @param bool $indicator 指標フラグ
     */
    public function __construct(
        public readonly int $lineId,
        public array $defectiveCounts,
        public readonly string $start,
        public readonly bool $countSwitch,
        public readonly int $cycleTimeMs,
        public readonly int $overTimeMs,
        public readonly array $plannedOutages,
        public array $changeovers,
        public readonly bool $indicator,
    ) {
        $this->at = $this->start;
        $this->jobKey = (string) Str::uuid();
    }

    /**
     * 生産数を取得する
     *
     * @return int 生産数
     */
    public function totalCount(): int
    {
        // Log::debug('Count Switch', [$this->countSwitch, $this->count]);
        if ($this->countSwitch) {
            return $this->count + $this->defectiveCount();
        } else {
            return $this->count;
        }
    }

    /**
     * 良品数を取得する
     *
     * @return int 良品数
     */
    public function goodCount(): int
    {
        return $this->totalCount() - $this->defectiveCount();
    }

    /**
     * 不良品数を取得する
     *
     * @return int 不良品数
     */
    public function defectiveCount(): int
    {
        return array_reduce($this->defectiveCounts, fn(int $total, int $count) => $total + $count, 0);
    }

    /**
     * 不良品数を設定する
     *
     * @param int $productionLineId 生産ラインID
     * @param int $count 不良品カウント
     * @return void
     */
    public function setDefectiveCount(int $productionLineId, int $count): void
    {
        if (array_key_exists($productionLineId, $this->defectiveCounts)) {
            $this->defectiveCounts[$productionLineId] = $count;
        } else {
            Log::warning('Production Lid ID not found.', ['productionLineId' => $productionLineId]);
        }
    }

    /**
     * 良品率を取得する(0~1)
     *
     * @return float 良品率
     */
    public function goodRate(): float
    {
        $totalCount = $this->totalCount();
        if ($totalCount <= 0) {
            return 0;
        } else {
            return ($totalCount - $this->defectiveCount()) / $totalCount;
        }
    }

    /**
     * 不良品率を取得する(0~1)
     *
     * @return float 不良品率
     */
    public function defectiveRate(): float
    {
        $totalCount = $this->totalCount();
        if ($totalCount <= 0) {
            return 0;
        } else {
            return $this->defectiveCount() / $totalCount;
        }
    }

    /**
     * 時間稼働率を取得する(0~1)
     *
     * @return float 時間稼働率
     */
    public function timeOperatingRate(): float
    {
        if ($this->loadingTime === 0) {
            return 0;
        } else {
            return $this->operatingTime / $this->loadingTime;
        }
    }

    /**
     * 性能稼働率を取得する(0~1)
     *
     * @return float 性能稼働率
     */
    public function performanceOperatingRate(): float
    {
        if ($this->operatingTime === 0) {
            return 0;
        } else {
            return $this->netTime / $this->operatingTime;
        }
    }

    /**
     * 設備総合効率を取得する(0~1)
     *
     * @return float 設備総合効率
     */
    public function overallEquipmentEffectiveness(): float
    {
        return $this->goodRate() * $this->timeOperatingRate() * $this->performanceOperatingRate();
    }

    /**
     * 計画値を取得する
     *
     * @return int 計画値
     */
    public function planCount(): int
    {
        return intdiv($this->operatingTime, $this->cycleTimeMs);
    }

    /**
     * 達成率を取得する(0~1)
     *
     * @return float 達成率
     */
    public function achievementRate(): float
    {
        $planCount = $this->planCount();
        if ($planCount === 0) {
            return 0;
        } else {
            return $this->goodCount() / $planCount;
        }
    }

    /**
     * サイクルタイム[sec]を取得する
     *
     * @return float サイクルタイム
     */
    public function cycleTime(): float
    {
        $breakDownCount = $this->breakDownCount();
        $totalCount = $this->totalCount();
        $productionCount = $totalCount - $this->autoResumeCount - $breakDownCount + ($this->isBreakdown() ? 1 : 0);
        if ($productionCount === 0) {
            return 0;
        } else {
            return max(0, ($this->netTime - $this->overTimeMs * $breakDownCount) / ($productionCount * 1000));
        }
    }

    /**
     * チョコ停回数を取得する
     *
     * @return int チョコ停回数
     */
    public function breakDownCount(): int
    {
        return count($this->breakdowns);
    }

    /**
     * チョコ停区間を取得する
     *
     * @return Collection<FromTo> チョコ停区間
     */
    public function breakDownSections(): Collection
    {
        return $this->arrayToCollection($this->breakdowns);
    }

    /**
     * ペイロードを更新する
     *
     * @param Carbon $date 更新時刻
     * @return void
     */
    public function update(Carbon $date): void
    {
        $this->updateWorkingTime($date);
        $this->plannedOutageSections = $this->plannedOutageSections($date);
        $this->changeoverSections = $this->arrayToCollection($this->changeovers);
        $this->breakdownSections = $this->arrayToCollection($this->breakdowns);
        $this->updateLoadingTime();
        $this->updateOperatingTime();
        $this->updateNetTime();
    }

    /**
     * 計画停止時間中かどうかを取得する
     *
     * @param Carbon|null $date
     * @return bool trueなら計画停止時間中
     */
    public function inPlannedOutage(Carbon $date = null): bool
    {
        if (is_null($date)) {
            if (is_null($this->plannedOutageSections)) {
                return false;
            } else {
                $at = Utility::parse($this->at);
                return !$this->plannedOutageSections
                    ->filter(fn(FromTo $x) => $x->from->lte($at) && $at->lte($x->to))
                    ->isEmpty();
            }
        } else {
            $this->plannedOutageSections = $this->plannedOutageSections($date);
            return !$this->plannedOutageSections
                ->filter(fn(FromTo $x) => $x->from->lte($date) && $date->lte($x->to))
                ->isEmpty();
        }
    }

    /**
     * 生産ステータスを取得する
     *
     * @return ProductionStatus 生産ステータス
     */
    public function status(): ProductionStatus
    {
        if ($this->isComplete) {
            return ProductionStatus::COMPLETE();
        } elseif ($this->isChangeover()) {
            return ProductionStatus::CHANGEOVER();
        } elseif ($this->isBreakdown()) {
            return ProductionStatus::BREAKDOWN();
        } else {
            return ProductionStatus::RUNNING();
        }
    }

    /**
     * 生産の完了処理
     *
     * @param Carbon $date 完了時刻
     * @return void
     */
    public function complete(Carbon $date): void
    {
        $this->isComplete = true;
        $this->addChangeover($date, false);
        $this->addBreakdown($date, false);
        $this->jobKey = '';
    }

    /**
     * 段取り替えの開始/終了時刻を追加する
     *
     * @param Carbon $date チョコ停の開始/終了時刻
     * @param bool $isStart trueならチョコ停の開始
     * @return void
     */
    public function addChangeover(Carbon $date, bool $isStart): void
    {
        $this->addDate($date, $isStart, $this->changeovers);
        // Log::debug('Changeover Sections', $this->changeovers);
        if ($isStart === true) {
            $this->addDate($date, false, $this->breakdowns);
        }
    }

    /**
     * チョコ停の開始/終了時刻を追加する
     *
     * @param Carbon $date チョコ停の開始/終了時刻
     * @param bool $isStart trueならチョコ停の開始
     * @return void
     */
    public function addBreakdown(Carbon $date, bool $isStart): void
    {
        $result = $this->addDate($date, $isStart, $this->breakdowns);
        if (!$result && !$this->isComplete) {
            Log::critical('Breakdown time is Conflict!', [$date, $this->breakdowns]);
        }
        // Log::debug('Breakdown Sections', $this->breakdowns);
    }

    /**
     * 操業時間[ms]を更新する
     *
     * @param Carbon $at 現在時刻
     * @return void
     */
    private function updateWorkingTime(Carbon $at): void
    {
        $this->at = Utility::format($at);
        $start = Utility::parse($this->start);
        $this->workingTime = $start->diffInMilliseconds($at);
        // Log::debug('Working Time [ms]', [$this->workingTime]);
    }

    /**
     * 負荷時間[ms]を更新する
     *
     * @return void
     */
    private function updateLoadingTime(): void
    {
        $this->loadingTime = $this->workingTime - $this->totalLoadingLossTime();
        // Log::debug('Loading Time [ms]', [$this->loadingTime]);
    }

    /**
     * 稼働時間[ms]を更新する
     *
     * @return void
     */
    private function updateOperatingTime(): void
    {
        $this->operatingTime = $this->workingTime - $this->totalStopLossTime();
        // Log::debug('Operating Time [ms]', [$this->operatingTime]);
    }

    /**
     * 正味稼働時間を更新する
     *
     * @return void
     */
    private function updateNetTime(): void
    {
        $this->netTime = $this->workingTime - $this->totalPerformanceLossTime();
        // Log::debug('Net Time [ms]', [$this->netTime]);
    }

    /**
     * 段取り替え中かどうかを取得する
     *
     * @return bool trueなら段取り替え中
     */
    private function isChangeover(): bool
    {
        $key = array_key_last($this->changeovers);
        $last = is_null($key) ? null : $this->changeovers[$key];
        return !is_null($last) && is_null($last['to']);
    }

    /**
     * チョコ停中かどうかを取得する
     *
     * @return bool trueならチョコ停中
     */
    private function isBreakdown(): bool
    {
        $key = array_key_last($this->breakdowns);
        $last = is_null($key) ? null : $this->breakdowns[$key];
        return !is_null($last) && is_null($last['to']);
    }

    /**
     * チョコ停/段取り替えの開始/終了時刻を追加する
     *
     * @param Carbon $date チョコ停/段取り替えの開始/終了時刻
     * @param bool $isStart trueなら開始
     * @param array<int, array{from: string, to: string|null}> $fromToArray 追加対象時刻リスト
     * @return bool 更新の有無
     */
    private function addDate(Carbon $date, bool $isStart, array &$fromToArray): bool
    {
        $key = array_key_last($fromToArray);
        $last = is_null($key) ? null : $fromToArray[$key];

        if ($isStart) {
            $from = Utility::format($date);
            if (is_null($last) || (!is_null($last['to']) && strcmp($last['to'], $from) <= 0)) {
                array_push($fromToArray, [
                    'from' => $from,
                    'to' => null,
                ]);
                $this->update($date);
                return true;
            } else {
                return false;
            }
        } else {
            $to = Utility::format($date);
            // 直前が開放区間で、かつ from <= to の場合のみ終了区間として確定する。
            if (!is_null($last) && is_null($last['to']) && strcmp($last['from'], $to) <= 0) {
                array_pop($fromToArray);
                array_push($fromToArray, [
                    'from' => $last['from'],
                    'to' => $to,
                ]);
                $this->update($date);
                return true;
            } else {
                return false;
            }
        }
    }

    /**
     * 計画停止時間区間を更新する
     *
     * @param Carbon $date 現在時刻
     * @return Collection<FromTo>
     */
    private function plannedOutageSections(Carbon $date): Collection
    {
        // 開始日
        $start = Utility::parse($this->start);
        $startDay = $start->copy()->startOfDay();

        // 現在日
        $endDay = $date->copy()->startOfDay();

        $plannedOutages = $this->plannedOutages();
        $sections = collect();
        while (true) {
            if ($startDay->gt($endDay)) {
                break;
            }
            foreach ($plannedOutages as $plannedOutage) {
                $from = Utility::merge($startDay, $plannedOutage->from);
                $to = Utility::merge($startDay, $plannedOutage->to);
                if ($to < $from) {
                    $to->addDay();
                }
                if ($start < $to && $from < $date) {
                    $sections->push(new FromTo($from->max($start->copy()), $to->min($date->copy())));
                }
            }
            $startDay->addDay();
        }
        // Log::debug('Planned Outage Sections', $sections->toArray());
        return $sections;
    }

    /**
     * 計画停止合計時間(休止ロス)を取得する
     *
     * @return int 休止ロス[ms]
     */
    private function totalLoadingLossTime(): int
    {
        return $this->plannedOutageSections->reduce(fn(int $result, FromTo $value) => $result + $value->span(), 0);
    }

    /**
     * 計画停止時間と段取り替え時間の合計時間(停止ロス)を取得する
     *
     * @return int 停止ロス[ms]
     */
    private function totalStopLossTime(): int
    {
        $mergedSections = PayloadData::mergeSections($this->plannedOutageSections, $this->changeoverSections);
        return $mergedSections->reduce(fn(int $result, FromTo $value) => $result + $value->span(), 0);
    }

    /**
     * 計画停止時間と段取り替え時間とチョコ停時間の合計時間(性能ロス)を取得する
     *
     * @return int 性能ロス[ms]
     */
    private function totalPerformanceLossTime(): int
    {
        $mergedSections = PayloadData::mergeSections($this->plannedOutageSections, $this->changeoverSections, $this->breakdownSections);
        return $mergedSections->reduce(fn(int $result, FromTo $value) => $result + $value->span(), 0);
    }

    /**
     * 計画停止時間配列を時間区間オブジェクトのコレクションに変換する
     *
     * @return Collection<FromTo>
     */
    private function plannedOutages(): Collection
    {
        return collect(array_map(
            fn($x) => new FromTo(Utility::parse($x['startTime'], 'H:i:s'), Utility::parse($x['endTime'], 'H:i:s')),
            $this->plannedOutages
        ));
    }

    /**
     * 配列の時間区間データを時間区間データオブジェクトのコレクションに変換する
     *
     * @param array<array{from: string, to: string|null}> $array 配列の時間区間データ
     * @return Collection<FromTo> 時間区間データオブジェクトのコレクション
     */
    private function arrayToCollection(array $array): Collection
    {
        return collect(array_map(function ($x) {
            $from = Utility::parse($x['from']);
            $to = Utility::parse(is_null($x['to']) ? $this->at : $x['to']);
            return new FromTo($from, $to);
        }, $array));
    }

    /**
     * 指定した複数の区間の重複を合成した時間区間を合成する
     *
     * @param Collection<FromTo> ...$sections 時間区間
     * @return Collection<FromTo> 合成した時間区間
     */
    private static function mergeSections(Collection ...$sections): Collection
    {
        /** @var Collection<int, array{datetime: Carbon, isStart: bool}> $concatSections */
        $concatSections = collect($sections)
            ->flatten(1)
            ->map(function (FromTo $x) {
                return [
                    [
                        'datetime' => $x->from,
                        'isStart' => true,
                    ],
                    [
                        'datetime' => $x->to,
                        'isStart' => false,
                    ],
                ];
            })
            ->flatten(1)
            ->sortBy([
                ['datetime', 'asc'],
                ['isStart', 'desc'],
            ]);

        $start = Utility::now();
        $mergedSections = collect();
        $stackCount = 0;
        foreach ($concatSections as $section) {
            if ($section['isStart']) {
                if ($stackCount === 0) {
                    $start = $section['datetime'];
                }
                $stackCount++;
            } else {
                $stackCount--;
                if ($stackCount === 0) {
                    $mergedSections->push(new FromTo($start, $section['datetime']));
                }
            }
        }
        return $mergedSections;
    }
}
