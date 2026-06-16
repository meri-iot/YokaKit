<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\StoreCycleTimeRequest;
use App\Http\Requests\UpdateCycleTimeRequest;
use App\Models\CycleTime;
use App\Repositories\CycleTimeRepository;
use App\Repositories\PartNumberRepository;
use Illuminate\Support\Collection;

/**
 * サイクルタイム設定の選択肢生成と CRUD を束ねるサービス。
 */
class CycleTimeService
{
    /**
     * 必要なリポジトリを受け取る。
     */
    public function __construct(
        private readonly CycleTimeRepository $cycleTime,
        private readonly PartNumberRepository $partNumber,
    ) {}

    /**
     * 指定工程で未登録の品番だけを選択肢へ整形する。
     *
     * サイクルタイムに既に紐づく品番 ID を除外し、
     * 画面の select で扱いやすい ID => 品番名 の Collection を返す。
     *
     * @param int $processId 工程ID
     * @return Collection<int,string> 品番選択用のオプション
     */
    public function unusedPartNumberOptions(int $processId): Collection
    {
        $cycleTimes = $this->cycleTime->get(['process_id' => $processId], column: ['part_number_id']);
        $partNumbers = $this->partNumber->except($cycleTimes);

        /** @var Collection<int,string> */
        return $partNumbers->pluck('part_number_name', 'part_number_id');
    }

    /**
     * サイクルタイムを追加する
     *
     * @param StoreCycleTimeRequest $request サイクルタイム追加リクエスト
     * @return bool 成否
     */
    public function store(StoreCycleTimeRequest $request): bool
    {
        return $this->cycleTime->store($request);
    }

    /**
     * サイクルタイムを更新する
     *
     * @param UpdateCycleTimeRequest $request サイクルタイム更新リクエスト
     * @param CycleTime $cycleTime 更新対象のサイクルタイム
     * @return bool 成否
     */
    public function update(UpdateCycleTimeRequest $request, CycleTime $cycleTime): bool
    {
        return $this->cycleTime->update($request, $cycleTime);
    }

    /**
     * サイクルタイムを削除する
     *
     * @param CycleTime $cycleTime 削除対象のサイクルタイム
     * @return bool 成否
     */
    public function destroy(CycleTime $cycleTime): bool
    {
        return $this->cycleTime->destroy($cycleTime);
    }
}
