<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\ProcessPlannedOutage;

/**
 * 工程と計画停止時間の関連リポジトリ
 *
 * 工程に紐づく計画停止時間の登録・取得・削除を担う。
 * 中間テーブル(process_planned_outages)を操作し、
 * 工程ごとの計画停止時間の割り当てを管理する。
 *
 * @extends AbstractRepository<ProcessPlannedOutage>
 */
class ProcessPlannedOutageRepository extends AbstractRepository
{
    /**
     * このリポジトリが扱うモデルクラスを返す
     *
     * @return class-string<ProcessPlannedOutage>
     */
    public function model(): string
    {
        return ProcessPlannedOutage::class;
    }
}
