<?php

declare(strict_types=1);

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
use Illuminate\Support\Facades\DB;

/**
 * 品番&作業者入れ替えサービス
 */
class SwitchService
{
    /**
     * コンストラクタ
     *
     * 複数リポジトリを明示的に依存させることで、テスト容易性と
     * 依存関係の可読性を高める。
     */
    public function __construct(
        private readonly LineRepository $line,
        private readonly ProcessRepository $process,
        private readonly ProducerRepository $producer,
        private readonly ProductionLineRepository $productionLine,
        private readonly WorkerRepository $worker,
    ) {}

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
     * リクエストで指定されたラインの作業者を更新し、
     * 稼働中の生産ラインについては生産者（プロデューサー）も連動させる。
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
                $workerId = ($line['worker_id'] === '' || is_null($line['worker_id']))
                    ? null
                    : (int) $line['worker_id'];
                $lineId = (int) $line['line_id'];

                // 1) ラインの作業者を更新（稼働状態に関わらず常に実行）
                $this->line->updateWorker($lineId, $workerId);

                // 2) 生産中でなければ次のラインへ
                if (is_null($productionLines)) {
                    continue;
                }

                // 3) 稼働中の生産ラインを取得
                $productionLine = $this->productionLine->first([
                    'line_id' => $lineId,
                    'production_history_id' => $history->production_history_id,
                ]);

                // 4) 該当する生産ラインが見つからない場合は次へ
                if (is_null($productionLine)) {
                    continue;
                }

                // 5) 不良品のラインならば次へ（生産者変更対象外）
                if ($productionLine->defective === true) {
                    continue;
                }

                // 6) 対象生産ラインの生産者を取得
                $producer = $this->producer->findBy($productionLine->production_line_id);

                // 7) 生産者の切り替えロジック：既存生産者の有無と新規作業者の有無で分岐
                $result = true;
                if (!is_null($producer) && !is_null($workerId) && $producer->worker_id != $workerId) {
                    // 7a) 既存生産者を新規作業者に置き換え
                    $this->producer->stop($producer, $now);
                    $worker = $this->worker->find($workerId);
                    $result = $this->producer->save($worker, $productionLine->production_line_id, $now);
                } else if (is_null($producer) && !is_null($workerId)) {
                    // 7b) 生産者がいない場合は新規登録
                    $worker = $this->worker->find($workerId);
                    $result = $this->producer->save($worker, $productionLine->production_line_id, $now);
                } else if (!is_null($producer) && is_null($workerId)) {
                    // 7c) 既存生産者を削除（作業者なしへ）
                    $this->producer->stop($producer, $now);
                }

                if (!$result) {
                    throw new Exception();
                }
            }
        });
    }
}
