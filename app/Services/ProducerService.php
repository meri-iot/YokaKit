<?php

namespace App\Services;

use App\Repositories\WorkerRepository;
use Illuminate\Support\Facades\App;

/**
 * 生産者サービス
 */
class ProducerService
{
    private readonly WorkerRepository $worker;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->worker = App::make(WorkerRepository::class);
    }

    /**
     * 作業者選択用のオプションを取得する
     *
     * @return array<int,string> 作業者選択用のオプション
     */
    public function workerOptions(): array
    {
        return $this->worker->options();
    }
}
