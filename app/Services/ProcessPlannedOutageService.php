<?php

namespace App\Services;

use App\Http\Requests\StoreProcessPlannedOutageRequest;
use App\Models\PlannedOutage;
use App\Models\ProcessPlannedOutage;
use App\Repositories\PlannedOutageRepository;
use App\Repositories\ProcessPlannedOutageRepository;
use Illuminate\Support\Facades\App;

/**
 * 工程-計画停止時間のサービス
 */
class ProcessPlannedOutageService
{
    private readonly PlannedOutageRepository $plannedOutage;
    private readonly ProcessPlannedOutageRepository $processPlannedOutage;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->plannedOutage = App::make(PlannedOutageRepository::class);
        $this->processPlannedOutage = App::make(ProcessPlannedOutageRepository::class);
    }

    /**
     * 指定した工程IDで使用されていない計画停止時間選択用のオプションを取得する
     *
     * @param integer $processId 工程ID
     * @return array<int,string> 計画停止時間選択用のオプション
     */
    public function unusedPlannedOutageOptions(int $processId): array
    {
        $processPlannedOutages = $this->processPlannedOutage->get(['process_id' => $processId], column: ['planned_outage_id']);
        $plannedOutages = $this->plannedOutage->except($processPlannedOutages);
        return $plannedOutages->reduce(function (array $carry, PlannedOutage $plannedOutage) {
            $carry[$plannedOutage->planned_outage_id] = "{$plannedOutage->planned_outage_name} : {$plannedOutage->formatStartTime()} ~ {$plannedOutage->formatEndTime()}";
            return $carry;
        }, []);
    }

    /**
     * 工程計画停止時間を追加する
     *
     * @param StoreProcessPlannedOutageRequest $request 工程計画停止時間追加リクエスト
     * @return boolean 成否
     */
    public function store(StoreProcessPlannedOutageRequest $request): bool
    {
        return $this->processPlannedOutage->store($request);
    }

    /**
     * 工程計画停止時間を削除する
     *
     * @param ProcessPlannedOutage $processPlannedOutage
     * @return boolean 成否
     */
    public function destroy(ProcessPlannedOutage $processPlannedOutage): bool
    {
        return $this->processPlannedOutage->destroy($processPlannedOutage);
    }
}
