<?php

namespace App\Repositories;

use App\Models\AndonConfig;
use Illuminate\Support\Facades\Auth;

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
     * @return class-string
     */
    public function model(): string
    {
        return AndonConfig::class;
    }

    /**
     * アンドン設定を取得する
     *
     * @return AndonConfig アンドン設定
     */
    public function andonConfig(): AndonConfig
    {
        $userId = Auth::id();
        $config = $this->first(['user_id' => $userId]);
        if (is_null($config)) {
            $config = new AndonConfig(['user_id' => $userId]);
            $this->storeModel($config);
        }
        return $config;
    }
}
