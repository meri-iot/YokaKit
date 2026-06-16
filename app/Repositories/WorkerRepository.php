<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Worker;
use Illuminate\Database\Eloquent\Collection;

/**
 * 作業者リポジトリ
 *
 * @extends AbstractRepository<Worker>
 */
class WorkerRepository extends AbstractRepository
{
    /**
     * このリポジトリが扱うモデルクラスを返す
     *
     * @return class-string<Worker>
     */
    public function model(): string
    {
        return Worker::class;
    }

    /**
     * 作業者選択用のオプションを取得する
     *
     * 先頭に未選択用の空要素を含めたうえで、識別番号順の選択肢を返す。
     *
     * @return array<int|string,string> 作業者選択用のオプション
     */
    public function options(): array
    {
        /** @var Collection<int,Worker> */
        $workers = $this->all(order: 'identification_number');
        return $workers->reduce(function (array $carry, Worker $worker) {
            $carry[$worker->worker_id] = "{$worker->identification_number} : {$worker->worker_name}";
            return $carry;
        }, ['' => '']);
    }
}
