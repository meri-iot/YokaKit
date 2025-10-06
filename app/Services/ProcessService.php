<?php

namespace App\Services;

use App\Http\Requests\StoreProcessRequest;
use App\Http\Requests\UpdateProcessRequest;
use App\Models\Process;
use App\Models\ProductionLine;
use App\Repositories\ProcessRepository;
use App\Repositories\ProductionLineRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\App;

/**
 * 工程サービス
 */
class ProcessService
{
    private readonly ProcessRepository $process;
    private readonly ProductionLineRepository $productionLine;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->process = App::make(ProcessRepository::class);
        $this->productionLine = App::make(ProductionLineRepository::class);
    }

    /**
     * すべての工程を取得する
     *
     * @return Collection<int,Process>
     */
    public function all()
    {
        return $this->process->all('productionHistory.indicatorLine.payload');
    }

    /**
     * すべての工程情報を取得する
     *
     * @return SupportCollection<int, array<string,mixed>>
     */
    public function allProcessInfo(): SupportCollection
    {
        /** @var Collection<int,Process> */
        $processes = $this->process->all('partNumbers');
        return $processes->map(fn(Process $p) => $p->info());
    }

    /**
     * 工程を追加する
     *
     * @param StoreProcessRequest $request 工程追加リクエスト
     * @return boolean 成否
     */
    public function store(StoreProcessRequest $request): bool
    {
        return $this->process->store($request);
    }

    /**
     * 工程を更新する
     *
     * @param UpdateProcessRequest $request 工程更新リクエスト
     * @param Process $process 更新対象の工程
     * @return boolean 成否
     */
    public function update(UpdateProcessRequest $request, Process $process): bool
    {
        return $this->process->update($request, $process);
    }

    /**
     * 工程を削除する
     *
     * @param Process $process 削除対象の工程
     * @return boolean 成否
     */
    public function destroy(Process $process): bool
    {
        return $this->process->destroy($process);
    }

    /**
     * 生産数を取得する
     *
     * @param Process $process 工程
     * @return Collection<int,ProductionLine>|null 生産数
     */
    public function productionLines(Process $process): ?Collection
    {
        if ($process->isStopped()) {
            return null;
        } else {
            return $this->productionLine->get(
                ['production_history_id' => $process->production_history_id],
                ['productions', 'defectiveProductions'],
                order: 'order'
            );
        }
    }
}
