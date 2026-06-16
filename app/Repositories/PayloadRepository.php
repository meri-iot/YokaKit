<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Data\FromTo;
use App\Data\PayloadData;
use App\Models\Payload;
use App\Models\ProductionLine;
use App\Services\Utility;
use Illuminate\Database\Eloquent\ModelNotFoundException;
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
     * @return class-string<Payload>
     */
    public function model(): string
    {
        return Payload::class;
    }

    /**
     * 指標を取得する
     *
     * @param ProductionLine $productionLine
     * @throws ModelNotFoundException ペイロードが存在しない場合
     * @return Payload
     */
    public function getPayload(ProductionLine $productionLine): Payload
    {
        $payload = $productionLine->defective
            ? $this->first(['production_line_id' => $productionLine->parent_id])
            : $this->first(['production_line_id' => $productionLine->production_line_id]);

        Utility::ensureModelExists($payload);

        /** @var Payload $payload */
        return $payload;
    }

    /**
     * 指標を更新する
     *
     * @param ProductionLine|Payload $data
     * @param callable(PayloadData $payloadData):void $callable コールバック処理
     * @throws ModelNotFoundException ペイロード更新に失敗した場合
     * @return PayloadData ペイロードデータ
     */
    public function updatePayload(ProductionLine|Payload $data, callable $callable): PayloadData
    {
        $payload = $data instanceof ProductionLine ? $this->getPayload($data) : $data;
        $payloadData = $payload->getPayloadData();

        if (!$payloadData->isComplete) {
            $callable($payloadData);
            $payload->setPayloadData($payloadData);
            $result = $this->updateModel($payload);
            Utility::ensureOperationSucceeded($payload, $result);
        }

        return $payloadData;
    }

    /**
     * 指標計算用データを新規に作成する。
     *
     * @param int $productionLineId 生産ラインのID
     * @param array<int,int> $defectiveLineIds 不良品ラインのID
     * @param Carbon $date 生産開始日時
     * @param array<int, array{startTime: string, endTime: string}> $plannedOutages 計画停止時間の開始と終了の配列
     * @param bool $changeover trueの場合段取り替えから開始
     * @param bool $countSwitch カウント切替
     * @param int $cycleTimeMs 標準サイクルタイム[ms]
     * @param int $overTimeMs オーバータイム[ms]
     * @param bool $indicator trueの場合生産指標となる
     * @throws ModelNotFoundException 保存に失敗した場合
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

        $result = $this->storeModel($payload);
        Utility::ensureOperationSucceeded($payload, $result);

        return $payload;
    }
}
