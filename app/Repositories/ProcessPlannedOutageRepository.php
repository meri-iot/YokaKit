<?php

namespace App\Repositories;

use App\Models\ProcessPlannedOutage;
use Illuminate\Database\Eloquent\Collection;

/**
 * 工程と計画停止時間の関連リポジトリ
 *
 * @extends AbstractRepository<ProcessPlannedOutage>
 */
class ProcessPlannedOutageRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string
     */
    public function model(): string
    {
        return ProcessPlannedOutage::class;
    }
}
