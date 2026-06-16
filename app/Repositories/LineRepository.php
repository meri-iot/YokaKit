<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Line;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * 作業リポジトリ
 *
 * @extends AbstractRepository<Line>
 */
class LineRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string<Line>
     */
    public function model(): string
    {
        return Line::class;
    }

    /**
     * 指定工程の不良品ではない作業選択肢を返す
     *
     * 先頭に未選択用の空要素を含める。
     *
     * @param int $processId 工程ID
     * @return array<int|string,string>
     */
    public function nonDefectiveOptions(int $processId): array
    {
        /** @var \Illuminate\Database\Eloquent\Collection<int,Line> $lines */
        $lines = $this->get([
            'process_id' => $processId,
            'defective' => false,
        ]);

        return $lines->reduce(function (array $carry, Line $line) {
            $carry[$line->line_id] = $line->line_name;
            return $carry;
        }, ['' => '']);
    }

    /**
     * 作業並べ替えを行う
     *
     * 渡された作業ID一覧の配列インデックスを新しい並び順として保存する。
     * 作業が存在しない、または対象工程に属さない場合はトランザクションを
     * ロールバックして例外を投げる。
     *
     * @param int $processId 工程ID
     * @param array<int,int> $orders 並び順に並んだ作業ID一覧
     * @throws ModelNotFoundException 作業が存在しない場合
     */
    public function sort(int $processId, array $orders): void
    {
        DB::transaction(function () use ($processId, $orders) {
            foreach ($orders as $index => $lineId) {
                $line = $this->model
                    ->where('line_id', $lineId)
                    ->where('process_id', $processId)
                    ->first();

                if (is_null($line)) {
                    throw new ModelNotFoundException();
                }

                // `order` は fillable に含まれるため updateModel で更新できる。
                // Builder 経由だと「更新 0 件 = 失敗」と誤判定されるため、
                // モデルインスタンスに対して行うことで正確に成否を判定する。
                $result = $this->updateModel($line, ['order' => $index]);
                if (!$result) {
                    throw new ModelNotFoundException();
                }
            }
        });
        Log::info('Order is updated', [
            'process_id' => $processId,
            'orders' => $orders,
        ]);
    }

    /**
     * 作業の作業者を更新する
     *
     * @param int $lineId 作業ID
     * @param int|null $workerId 作業者ID。null の場合は担当なしにする
     * @return bool 成否。作業未存在時は false を返す
     */
    public function updateWorker(int $lineId, int|null $workerId): bool
    {
        $line = $this->model->find($lineId);
        if (is_null($line)) {
            return false;
        }

        return $this->updateModel($line, ['worker_id' => $workerId]);
    }
}
