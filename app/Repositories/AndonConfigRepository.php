<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\AndonConfig;

/**
 * アンドン設定用リポジトリ
 *
 * @extends AbstractRepository<AndonConfig>
 */
class AndonConfigRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string<AndonConfig>
     */
    public function model(): string
    {
        return AndonConfig::class;
    }

    /**
     * 指定ユーザーのアンドン設定を取得する
     *
     * レコードが存在しない場合はデフォルト値で新規作成して返す。
     *
     * @param int $userId ユーザーID
     * @return AndonConfig アンドン設定
     */
    public function findOrCreateByUserId(int $userId): AndonConfig
    {
        $config = $this->first(['user_id' => $userId]);

        if (is_null($config)) {
            $config = new AndonConfig(['user_id' => $userId]);
            $this->storeModel($config);
        }

        return $config;
    }
}
