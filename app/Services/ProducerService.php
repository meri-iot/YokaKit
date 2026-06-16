<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\WorkerRepository;

/**
 * 生産時に利用する作業者選択ユースケースを扱うサービス
 */
class ProducerService
{
    /**
     * コンストラクタ
     */
    public function __construct(
        private readonly WorkerRepository $worker,
    ) {}

    /**
     * 作業者選択用のオプションを取得する
     *
     * @return array<int|string,string> 作業者選択用のオプション
     */
    public function workerOptions(): array
    {
        return $this->worker->options();
    }
}
