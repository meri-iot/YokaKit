<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\StoreProcessPlannedOutageRequest;
use App\Models\PlannedOutage;
use App\Models\ProcessPlannedOutage;
use App\Repositories\PlannedOutageRepository;
use App\Repositories\ProcessPlannedOutageRepository;

/**
 * 工程と計画停止時間の関連付けユースケースを扱うサービス
 */
class ProcessPlannedOutageService
{
    /**
     * コンストラクタ
     */
    public function __construct(
        private readonly PlannedOutageRepository $plannedOutage,
        private readonly ProcessPlannedOutageRepository $processPlannedOutage,
    ) {}

    /**
     * 指定した工程IDで使用されていない計画停止時間選択用のオプションを取得する
     *
     * @param int $processId 工程ID
     * @return array<int,string> 計画停止時間選択用のオプション
     */
    public function unusedPlannedOutageOptions(int $processId): array
    {
        // 工程に割り当て済みの計画停止時間IDを取得する
        $processPlannedOutages = $this->processPlannedOutage->get(['process_id' => $processId], column: ['planned_outage_id']);

        // 未割り当て候補を取得し、選択肢表示用のラベルへ整形する
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
     * @return bool 成否
     */
    public function store(StoreProcessPlannedOutageRequest $request): bool
    {
        return $this->processPlannedOutage->store($request);
    }

    /**
     * 工程計画停止時間を削除する
     *
     * @param ProcessPlannedOutage $processPlannedOutage
     * @return bool 成否
     */
    public function destroy(ProcessPlannedOutage $processPlannedOutage): bool
    {
        return $this->processPlannedOutage->destroy($processPlannedOutage);
    }
}
