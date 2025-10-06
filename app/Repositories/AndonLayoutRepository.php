<?php

namespace App\Repositories;

use App\Models\AndonLayout;
use Illuminate\Support\Facades\Log;

/**
 * アンドンレイアウト設定用リポジトリ
 *
 * @extends AbstractRepository<AndonLayout>
 */
class AndonLayoutRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string
     */
    public function model(): string
    {
        return AndonLayout::class;
    }

    /**
     * レイアウト設定を更新する
     *
     * @param array<int, array{display?: bool}> $layouts レイアウト
     * @param integer $userId ユーザーID
     * @return boolean 成否
     */
    public function updateLayouts(array $layouts, int $userId): bool
    {
        $order = 0;
        foreach ($layouts as $processId => $layout) {
            $isDisplay = isset($layout['display']);
            $layout = $this->first([
                'user_id' => $userId,
                'process_id' => $processId,
            ]);
            if (is_null($layout)) {
                $l = new AndonLayout([
                    'user_id' => $userId,
                    'process_id' => $processId,
                    'order' => $order,
                    'is_display' => $isDisplay,
                ]);
                $result = $this->storeModel($l);
                if (!$result) {
                    return false;
                }
            } else {
                $result = $this->updateModel($layout, [
                    'order' => $order,
                    'is_display' => $isDisplay,
                ]);
                if (!$result) {
                    return false;
                }
            }
            $order++;
        }
        Log::info("{$this->model()} is updated", ['user_id' => $userId, 'layout' => $layouts]);
        return true;
    }
}
