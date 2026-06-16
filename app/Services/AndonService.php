<?php

declare(strict_types=1);

namespace App\Services;

use App\Data\PayloadData;
use App\Http\Requests\UpdateAndonConfigRequest;
use App\Models\AndonConfig;
use App\Models\Process;
use App\Models\ProductionHistory;
use App\Repositories\AndonConfigRepository;
use App\Repositories\AndonLayoutRepository;
use App\Repositories\ProcessRepository;
use App\Repositories\ProductionHistoryRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/**
 * アンドン画面向けの工程一覧と表示設定を扱うサービス。
 */
class AndonService
{
    /**
     * 必要なリポジトリを受け取り、画面表示用の集計を組み立てる。
     */
    public function __construct(
        private readonly AndonConfigRepository $andonConfig,
        private readonly AndonLayoutRepository $andonLayout,
        private readonly ProcessRepository $process,
        private readonly ProductionHistoryRepository $productionHistory,
    ) {}

    /**
     * アンドン画面向けの工程一覧を取得する。
     *
     * 稼働中の生産履歴と payload がそろっている工程だけに
     * production_summary を付与し、表示順で並べ替える。
     *
     * @return Collection<int,Process>
     */
    public function processes(): Collection
    {
        return $this->buildProcessesWithSummary(
            [
                'andonLayout',
                'sensorEvents',
                'productionHistory.indicatorLine.payload',
            ],
            'production_summary',
            fn(ProductionHistory $history, PayloadData $payloadData): array => $this->productionHistory->makeProductionSummary($history, $payloadData)->toArray(),
            [
                ['andonLayout.order', 'asc'],
                ['process_id', 'asc'],
            ],
        );
    }

    /**
     * API 向けの工程指標一覧を取得する。
     *
     * payload がそろっている工程だけに summary を付与し、工程 ID 順で返す。
     *
     * @return Collection<int,Process>
     */
    public function indicators(): Collection
    {
        return $this->buildProcessesWithSummary(
            [
                'productionHistory.indicatorLine.payload',
            ],
            'summary',
            fn(ProductionHistory $history, PayloadData $payloadData): array => $this->productionHistory->makeIndicatorSummary($payloadData),
            [
                ['process_id', 'asc'],
            ],
        );
    }

    /**
     * アンドン設定を取得する
     *
     * @return AndonConfig
     */
    public function andonConfig(int $userId): AndonConfig
    {
        return $this->andonConfig->findOrCreateByUserId($userId);
    }

    /**
     * アンドン設定を更新する
     *
     * @param UpdateAndonConfigRequest $request
     * @throws ModelNotFoundException
     */
    public function update(UpdateAndonConfigRequest $request, int $userId): void
    {
        DB::transaction(function () use ($request, $userId) {
            $config = $this->andonConfig($userId);
            $result = $this->andonConfig->update($request, $config);
            Utility::ensureOperationSucceeded($config, $result);

            if (!is_null($request->layouts) && !$this->andonLayout->updateLayouts($request->layouts, $userId)) {
                throw new ModelNotFoundException();
            }
        });
    }

    /**
     * 工程一覧へ summary 属性を付与して整形する。
     *
     * @param array<int,string> $relations
     * @param callable(ProductionHistory, PayloadData): array<string,mixed> $summaryBuilder
     * @param array<int, array{0:string,1:string}> $sorts
     * @return Collection<int,Process>
     */
    private function buildProcessesWithSummary(array $relations, string $summaryAttribute, callable $summaryBuilder, array $sorts): Collection
    {
        /** @var Collection<int,Process> $processes */
        $processes = $this->process->all($relations)
            ->map(function (Process $process) use ($summaryAttribute, $summaryBuilder): Process {
                $history = $process->productionHistory;
                $payloadData = $this->payloadData($process);

                if (!is_null($history) && !is_null($payloadData)) {
                    $process->setAttribute($summaryAttribute, $summaryBuilder($history, $payloadData));
                }

                return $process;
            })
            ->sortBy($sorts)
            ->values();

        return $processes;
    }

    /**
     * 工程から指標計算用 payload を安全に取り出す。
     */
    private function payloadData(Process $process): ?PayloadData
    {
        return $process->productionHistory?->indicatorLine?->payload?->getPayloadData();
    }
}
