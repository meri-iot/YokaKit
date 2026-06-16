<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\SortLineRequest;
use App\Http\Requests\StoreLineRequest;
use App\Http\Requests\UpdateLineRequest;
use App\Models\Line;
use App\Models\Process;
use App\Repositories\LineRepository;
use App\Repositories\RaspberryPiRepository;
use App\Repositories\WorkerRepository;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * 作業関連ユースケースをまとめるサービス
 */
class LineService
{
    /**
     * コンストラクタ
     */
    public function __construct(
        private readonly LineRepository $line,
        private readonly RaspberryPiRepository $raspberryPi,
        private readonly WorkerRepository $worker,
    ) {}

    /**
     * 不良品ではない作業選択用のオプションを取得する
     *
     * @param Process $process 工程
     * @return array<int|string,string>
     */
    public function nonDefectiveLineOptions(Process $process): array
    {
        return $this->line->nonDefectiveOptions($process->process_id);
    }

    /**
     * ラズベリーパイ選択用のオプションを取得する
     *
     * @return array<int,string> ラズベリーパイ選択用のオプション
     */
    public function raspberryPiOptions(): array
    {
        return $this->raspberryPi->options();
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
     * 作業を追加する
     *
     * @param StoreLineRequest $request 作業追加リクエスト
     * @return bool 成否
     */
    public function store(StoreLineRequest $request): bool
    {
        return $this->line->store($request);
    }

    /**
     * 作業を更新する
     *
     * @param UpdateLineRequest $request 作業更新リクエスト
     * @param Line $line 更新対象の作業
     * @return bool 成否
     */
    public function update(UpdateLineRequest $request, Line $line): bool
    {
        return $this->line->update($request, $line);
    }

    /**
     * 作業を削除する
     *
     * @param Line $line 削除対象の作業
     * @return bool 成否
     */
    public function destroy(Line $line): bool
    {
        return $this->line->destroy($line);
    }

    /**
     * 作業の並べ替えを行う
     *
     * @param SortLineRequest $request 作業並べ替えリクエスト
     * @param Process $process 工程
     * @throws ModelNotFoundException
     */
    public function sort(SortLineRequest $request, Process $process): void
    {
        $this->line->sort($process->process_id, $request->order);
    }
}
