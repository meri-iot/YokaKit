<?php

namespace App\Repositories;

use App\Models\OnOff;

/**
 * ON-OFFメッセージリポジトリ
 *
 * @extends AbstractRepository<OnOff>
 */
class OnOffRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string
     */
    public function model(): string
    {
        return OnOff::class;
    }
}
