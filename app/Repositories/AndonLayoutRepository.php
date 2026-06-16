<?php

declare(strict_types=1);

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
     * @return class-string<AndonLayout>
     */
    public function model(): string
    {
        return AndonLayout::class;
    }

    /**
     * レイアウト設定を更新する
     *
     * レイアウト配列の順序をそのまま表示順として保存する。
     * process_id は配列キーではなく入力値を優先して使用する。
     *
     * @param array<int|string, array{display?: mixed, process_id?: int}> $layouts レイアウト
     * @param int $userId ユーザーID
     * @return bool 成否
     */
    public function updateLayouts(array $layouts, int $userId): bool
    {
        $order = 0;
        foreach ($layouts as $fallbackProcessId => $layoutInput) {
            $processId = (int) ($layoutInput['process_id'] ?? $fallbackProcessId);
            $isDisplay = isset($layoutInput['display']);
            $existingLayout = $this->first([
                'user_id' => $userId,
                'process_id' => $processId,
            ]);

            if (is_null($existingLayout)) {
                $layout = new AndonLayout([
                    'user_id' => $userId,
                    'process_id' => $processId,
                    'order' => $order,
                    'is_display' => $isDisplay,
                ]);

                $result = $this->storeModel($layout);
                if (!$result) {
                    return false;
                }
            } else {
                $result = $this->updateModel($existingLayout, [
                    'order' => $order,
                    'is_display' => $isDisplay,
                ]);

                if (!$result) {
                    return false;
                }
            }

            $order++;
        }

        Log::info("{$this->model()} is updated", ['user_id' => $userId, 'layouts' => $layouts]);

        return true;
    }
}
