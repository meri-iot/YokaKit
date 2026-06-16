<?php

declare(strict_types=1);

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
     * 共通 CRUD を ON-OFF メッセージ設定に適用するためのバインディング。
     *
     * @return class-string<OnOff>
     */
    public function model(): string
    {
        return OnOff::class;
    }
}
