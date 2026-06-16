<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\DefectiveProduction;
use App\Services\Utility;
use Illuminate\Support\Carbon;

/**
 * 不良品生産数リポジトリ
 *
 * @extends AbstractRepository<DefectiveProduction>
 */
class DefectiveProductionRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string<DefectiveProduction>
     */
    public function model(): string
    {
        return DefectiveProduction::class;
    }

    /**
     * 不良品生産数を新規保存する
     *
     * 保存用の日時はアプリケーション標準フォーマットへ正規化してから永続化する。
     * 保存成功時は作成済みモデル、失敗時は null を返す。
     *
     * @param int $productionLineId 生産ラインID
     * @param int $count カウント
     * @param Carbon $date 時刻
     * @return DefectiveProduction|null 追加されたデータ
     */
    public function save(int $productionLineId, int $count, Carbon $date): ?DefectiveProduction
    {
        $defectiveProduction = new DefectiveProduction([
            'production_line_id' => $productionLineId,
            'count' => $count,
            'at' => Utility::format($date),
        ]);

        return $this->storeModel($defectiveProduction) ? $defectiveProduction : null;
    }
}
