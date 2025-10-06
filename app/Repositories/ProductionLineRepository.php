<?php

namespace App\Repositories;

use App\Exceptions\ProductionException;
use App\Models\Line;
use App\Models\ProductionLine;
use Illuminate\Support\Facades\Log;

/**
 * 生産ラインリポジトリ
 *
 * @extends AbstractRepository<ProductionLine>
 */
class ProductionLineRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string
     */
    public function model(): string
    {
        return ProductionLine::class;
    }

    /**
     * 生産ラインを追加する
     *
     * @param Line $line ライン
     * @param integer $productionHistoryId 生産履歴ID
     * @param string $ipAddress IPアドレス
     * @param boolean $indicator 指標
     * @return ProductionLine|null 追加された生産ライン (失敗時はnull)
     */
    public function save(Line $line, int $productionHistoryId, string $ipAddress, bool $indicator): ?ProductionLine
    {
        $pl = new ProductionLine([
            'line_id' => $line->line_id,
            'line_name' => $line->line_name,
            'chart_color' => $line->chart_color,
            'ip_address' => $ipAddress,
            'pin_number' => $line->pin_number,
            'defective' => $line->defective,
            'order' => $line->order,
            'indicator' => $indicator,
            'production_history_id' => $productionHistoryId,
        ]);
        return $this->storeModel($pl) ? $pl : null;
    }

    /**
     * 不良品ラインを追加する
     *
     * @param Line $line ライン
     * @param integer $productionHistoryId 生産履歴ID
     * @param string $ipAddress IPアドレス
     * @param int $productionLineId 関連する加工数量ライン
     * @return ProductionLine|null 追加された生産ライン (失敗時はnull)
     */
    public function saveDefectiveLine(Line $line, int $productionHistoryId, string $ipAddress, int $productionLineId): ?ProductionLine
    {
        $pl = new ProductionLine([
            'line_id' => $line->line_id,
            'parent_id' => $productionLineId,
            'line_name' => $line->line_name,
            'chart_color' => $line->chart_color,
            'ip_address' => $ipAddress,
            'pin_number' => $line->pin_number,
            'defective' => $line->defective,
            'order' => $line->order,
            'indicator' => false,
            'production_history_id' => $productionHistoryId,
        ]);
        return $this->storeModel($pl) ? $pl : null;
    }

    /**
     * 指定したIPアドレスとピン番号に一致する最新の生産ラインを取得する
     *
     * @param string $ipAddress IPアドレス
     * @param integer|string $pinNumber ピン番号
     * @return ProductionLine|null
     */
    public function lastProductionLine(string $ipAddress, int|string $pinNumber): ?ProductionLine
    {
        return $this->model
            ->where('ip_address', $ipAddress)
            ->where('pin_number', $pinNumber)
            ->with(['productionHistory', 'parentLine', 'payload'])
            ->latest()
            ->first();
    }

    /**
     * 生産ライン情報を更新する
     *
     * @param ProductionLine $productionLine 生産ライン
     * @param integer $count 生産カウント
     * @return integer オフセットカウント
     */
    public function updateLineInfo(ProductionLine $productionLine, int $count): int
    {
        // オフセットカウント
        $offsetCount = $productionLine->offset_count;

        if (is_null($offsetCount)) {
            // オフセットカウントが未設定の場合は更新
            $offsetCount = $count - 1;
            $result = $this->updateModel($productionLine, [
                'offset_count' => $offsetCount,
                'count' => 1,
            ]);
            if (!$result) {
                throw new ProductionException();
            }
        } else if ($productionLine->count < $count - $offsetCount) {
            // 最終カウントを更新
            $result = $this->updateModel($productionLine, ['count' => $count - $offsetCount]);
            if (!$result) {
                throw new ProductionException();
            }
        } else {
            // カウントの矛盾が発生
            Log::warning('Count is contradiction.', ['count' => $count]);
            throw new ProductionException();
        }

        return $offsetCount;
    }
}
