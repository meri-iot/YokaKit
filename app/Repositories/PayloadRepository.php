<?php

namespace App\Repositories;

use App\Data\FromTo;
use App\Data\PayloadData;
use App\Models\Payload;
use App\Models\ProductionLine;
use App\Services\Utility;
use Illuminate\Support\Carbon;

/**
 * 指標計算のデータリポジトリ
 *
 * @extends AbstractRepository<Payload>
 */
class PayloadRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string
     */
    public function model(): string
    {
        return Payload::class;
    }

    /**
     * 指標を取得する
     *
     * @param ProductionLine $productionLine
     * @return Payload
     */
    public function getPayload(ProductionLine $productionLine): Payload
    {
        if ($productionLine->defective) {
            // 不良品の場合
            $parentId = $productionLine->parent_id;
            return $this->first(['production_line_id' => $parentId]);
        } else {
            // 生産数の場合
            $productionLineId = $productionLine->production_line_id;
            return $this->first(['production_line_id' => $productionLineId]);
        }
    }

    /**
     * 指標を更新する
     *
     * @param ProductionLine|Payload $data
     * @param callable(PayloadData $payloadData):void $callable コールバック処理
     * @return PayloadData ペイロードデータ
     */
    public function updatePayload(ProductionLine|Payload $data, callable $callable): PayloadData
    {
        $payload = $data instanceof ProductionLine ? $this->getPayload($data) : $data;
        $payloadData = $payload->getPayloadData();
        if (!$payloadData->isComplete) {
            $callable($payloadData);
            $payload->setPayloadData($payloadData);
            $this->updateModel($payload);
        }
        return $payloadData;
    }

    /**
     * 指標計算用データを新規に作成する。
     *
     * @param integer $productionLineId 生産ラインのID
     * @param array<int,int> $defectiveLineIds 不良品ラインのID
     * @param Carbon $date 生産開始日時
     * @param array<int, array{startTime: string, endTime: string}> $plannedOutages 計画停止時間の開始と終了の配列
     * @param boolean $changeover trueの場合段取り替えから開始
     * @param boolean $countSwitch カウント切替
     * @param integer $cycleTimeMs 標準サイクルタイム[ms]
     * @param integer $overTimeMs オーバータイム[ms]
     * @param boolean $indicator trueの場合生産指標となる
     * @return Payload 指標計算用データ
     */
    public function create(int $productionLineId, array $defectiveLineIds, Carbon $date, array $plannedOutages, bool $changeover, bool $countSwitch, int $cycleTimeMs, int $overTimeMs, bool $indicator): Payload
    {
        $defectiveCounts = array_reduce($defectiveLineIds, function (array $carry, int $id) {
            $carry[$id] = 0;
            return $carry;
        }, []);

        $payload = new Payload([
            'production_line_id' => $productionLineId,
            'payload' => (new PayloadData(
                $productionLineId,
                $defectiveCounts,
                Utility::format($date),
                $countSwitch,
                $cycleTimeMs,
                $overTimeMs,
                $plannedOutages,
                $changeover ? [(new FromTo($date->copy()))->toArray()] : [],
                $indicator,
            ))->toJson(),
        ]);
        return $this->storeModel($payload) ? $payload : null;
    }
}
