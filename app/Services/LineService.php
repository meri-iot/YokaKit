<?php

namespace App\Services;

use App\Http\Requests\SortLineRequest;
use App\Http\Requests\StoreLineRequest;
use App\Http\Requests\UpdateLineRequest;
use App\Models\Line;
use App\Models\Process;
use App\Repositories\LineRepository;
use App\Repositories\RaspberryPiRepository;
use App\Repositories\WorkerRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\App;

/**
 * ラインサービス
 */
class LineService
{
    private readonly LineRepository $line;
    private readonly RaspberryPiRepository $raspberryPi;
    private readonly WorkerRepository $worker;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->line = App::make(LineRepository::class);
        $this->raspberryPi = App::make(RaspberryPiRepository::class);
        $this->worker = App::make(WorkerRepository::class);
    }

    /**
     * 不良品ではないライン選択用のオプションを取得する
     *
     * @param Process $process 工程
     * @return array<mixed,string>
     */
    public function nonDefectiveLineOptions(Process $process)
    {
        /** @var Collection<int,Line> */
        $lines = $this->line->get([
            'process_id' => $process->process_id,
            'defective' => false,
        ]);

        return $lines->reduce(function (array $carry, Line $line) {
            $carry[$line->line_id] = $line->line_name;
            return $carry;
        }, ['' => '']);
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
     * ラインを追加する
     *
     * @param StoreLineRequest $request ライン追加リクエスト
     * @return boolean 成否
     */
    public function store(StoreLineRequest $request): bool
    {
        return $this->line->store($request);
    }

    /**
     * ラインを更新する
     *
     * @param UpdateLineRequest $request ライン更新リクエスト
     * @param Line $line 更新対象のライン
     * @return boolean 成否
     */
    public function update(UpdateLineRequest $request, Line $line): bool
    {
        return $this->line->update($request, $line);
    }

    /**
     * ラインを削除する
     *
     * @param Line $line 削除対象のライン
     * @return boolean 成否
     */
    public function destroy(Line $line): bool
    {
        return $this->line->destroy($line);
    }

    /**
     * ラインの並べ替えを行う
     *
     * @param SortLineRequest $request ライン並べ替えリクエスト
     * @param Process $process 工程
     * @throws ModelNotFoundException
     */
    public function sort(SortLineRequest $request, Process $process): void
    {
        $this->line->sort($process->process_id, $request->order);
    }
}
