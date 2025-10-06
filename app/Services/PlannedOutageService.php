<?php

namespace App\Services;

use App\Http\Requests\StorePlannedOutageRequest;
use App\Http\Requests\UpdatePlannedOutageRequest;
use App\Models\PlannedOutage;
use App\Repositories\PlannedOutageRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\App;

/**
 * 計画停止時間サービス
 */
class PlannedOutageService
{
    private readonly PlannedOutageRepository $plannedOutage;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->plannedOutage = App::make(PlannedOutageRepository::class);
    }

    /**
     * 計画停止時間一覧を取得する
     *
     * @return Collection<int,PlannedOutage>
     */
    public function all(): Collection
    {
        return $this->plannedOutage->all();
    }

    /**
     * 計画停止時間を追加する
     *
     * @param StorePlannedOutageRequest $request 計画停止時間追加リクエスト
     * @return boolean 成否
     */
    public function store(StorePlannedOutageRequest $request): bool
    {
        return $this->plannedOutage->store($request);
    }

    /**
     * 計画停止時間を更新する
     *
     * @param UpdatePlannedOutageRequest $request 計画停止時間更新リクエスト
     * @param PlannedOutage $plannedOutage 更新対象の計画停止時間
     * @return boolean 成否
     */
    public function update(UpdatePlannedOutageRequest $request, PlannedOutage $plannedOutage): bool
    {
        return $this->plannedOutage->update($request, $plannedOutage);
    }

    /**
     * 計画停止時間を削除する
     *
     * @param PlannedOutage $plannedOutage 削除対象の計画停止時間
     * @return boolean 成否
     */
    public function destroy(PlannedOutage $plannedOutage): bool
    {
        return $this->plannedOutage->destroy($plannedOutage);
    }
}
