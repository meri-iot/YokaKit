<?php

namespace App\Services;

use App\Http\Requests\StoreCycleTimeRequest;
use App\Http\Requests\UpdateCycleTimeRequest;
use App\Models\CycleTime;
use App\Models\PartNumber;
use App\Repositories\CycleTimeRepository;
use App\Repositories\PartNumberRepository;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;

/**
 * サイクルタイムサービス
 */
class CycleTimeService
{
    private readonly CycleTimeRepository $cycleTime;
    private readonly PartNumberRepository $partNumber;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->cycleTime = App::make(CycleTimeRepository::class);
        $this->partNumber = App::make(PartNumberRepository::class);
    }

    /**
     * 指定した工程IDで使用されていない品番選択用のオプションを取得する
     *
     * @param integer $processId 工程ID
     * @return Collection<int,string> 品番選択用のオプション
     */
    public function unusedPartNumberOptions(int $processId): Collection
    {
        $cycleTimes = $this->cycleTime->get(['process_id' => $processId], column: ['part_number_id']);
        $partNumbers = $this->partNumber->except($cycleTimes);
        return $partNumbers->reduce(function (Collection $carry, PartNumber $partNumber) {
            $carry->put($partNumber->part_number_id, $partNumber->part_number_name);
            return $carry;
        }, collect());
    }

    /**
     * サイクルタイムを追加する
     *
     * @param StoreCycleTimeRequest $request サイクルタイム追加リクエスト
     * @return boolean 成否
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
     * @return boolean 成否
     */
    public function update(UpdateCycleTimeRequest $request, CycleTime $cycleTime): bool
    {
        return $this->cycleTime->update($request, $cycleTime);
    }

    /**
     * サイクルタイムを削除する
     *
     * @param CycleTime $cycleTime 削除対象のサイクルタイム
     * @return boolean 成否
     */
    public function destroy(CycleTime $cycleTime): bool
    {
        return $this->cycleTime->destroy($cycleTime);
    }
}
