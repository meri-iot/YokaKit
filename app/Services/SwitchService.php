<?php

namespace App\Services;

use App\Http\Requests\UpdateLineWorkerRequest;
use App\Models\Process;
use App\Models\Worker;
use App\Repositories\LineRepository;
use App\Repositories\ProcessRepository;
use App\Repositories\ProducerRepository;
use App\Repositories\ProductionLineRepository;
use App\Repositories\WorkerRepository;
use Exception;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;

/**
 * 品番&作業者入れ替えサービス
 */
class SwitchService
{
    private readonly LineRepository $line;
    private readonly ProcessRepository $process;
    private readonly ProducerRepository $producer;
    private readonly ProductionLineRepository $productionLine;
    private readonly WorkerRepository $worker;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->line = App::make(LineRepository::class);
        $this->process = App::make(ProcessRepository::class);
        $this->producer = App::make(ProducerRepository::class);
        $this->productionLine = App::make(ProductionLineRepository::class);
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

    /**
     * すべての作業者を取得する
     *
     * @return Collection<int,Worker>
     */
    public function workers(): Collection
    {
        return $this->worker->all();
    }

    /**
     * すべての工程と生産情報を取得する
     *
     * @return Collection<int,Process>
     */
    public function processes(): Collection
    {
        return $this->process->all(['partNumbers', 'lines', 'productionHistory.indicatorLine.payload']);
    }

    /**
     * 作業者の入れ替えを行う
     *
     * @param UpdateLineWorkerRequest $request 作業者入れ替えリクエスト
     * @param Process $process 入れ替え対象の工程
     * @throws Exception
     */
    public function updateLineWorker(UpdateLineWorkerRequest $request, Process $process): void
    {
        DB::transaction(function () use ($process, $request) {
            $history = $process->productionHistory;
            $productionLines = $history?->productionLines;
            $now = Utility::now();
            foreach ($request->lines as $line) {

                $workerId = $line['worker_id'];
                $lineId = $line['line_id'];

                // ラインの作業者を更新
                $this->line->updateWorker($lineId, $workerId);

                // 稼働中でないならば次へ
                if (is_null($productionLines)) {
                    continue;
                }

                // 稼働中の生産ラインを取得
                $productionLine = $this->productionLine->first([
                    'line_id' => $lineId,
                    'production_history_id' => $history->production_history_id,
                ]);

                // 不良品のラインならば次へ
                if ($productionLine->defective === true) {
                    continue;
                }

                // 生産者を取得
                $producer = $this->producer->findBy($productionLine->production_line_id);

                $result = true;
                if (!is_null($producer) && !is_null($workerId) && $producer->worker_id != $workerId) {
                    // 生産者を入れ替え
                    $this->producer->stop($producer, $now);
                    $worker = $this->worker->find($workerId);
                    $result = $this->producer->save($worker, $productionLine->production_line_id, $now);
                } else if (is_null($producer) && !is_null($workerId)) {
                    // 生産者新規登録
                    $worker = $this->worker->find($workerId);
                    $result = $this->producer->save($worker, $productionLine->production_line_id, $now);
                } else if (!is_null($producer) && is_null($workerId)) {
                    // 生産者不在
                    $this->producer->stop($producer, $now);
                }
                if (!$result) {
                    throw new Exception();
                }
            }
        });
    }
}
