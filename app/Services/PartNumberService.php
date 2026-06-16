<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\StorePartNumberRequest;
use App\Http\Requests\UpdatePartNumberRequest;
use App\Models\PartNumber;
use App\Repositories\PartNumberRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * 品番マスタの取得・更新ユースケースを扱うサービス
 */
class PartNumberService
{
    /**
     * コンストラクタ
     */
    public function __construct(
        private readonly PartNumberRepository $partNumber,
    ) {}

    /**
     * すべての品番を取得する
     *
     * @return Collection<int,PartNumber>
     */
    public function all(): Collection
    {
        return $this->partNumber->all();
    }

    /**
     * 品番を追加する
     *
     * @param StorePartNumberRequest $request 品番追加リクエスト
     * @return bool 成否
     */
    public function store(StorePartNumberRequest $request): bool
    {
        return $this->partNumber->store($request);
    }

    /**
     * 品番を更新する
     *
     * @param UpdatePartNumberRequest $request 品番更新リクエスト
     * @param PartNumber $partNumber 更新対象の品番
     * @return bool 成否
     */
    public function update(UpdatePartNumberRequest $request, PartNumber $partNumber): bool
    {
        return $this->partNumber->update($request, $partNumber);
    }

    /**
     * 品番を削除する
     *
     * @param PartNumber $partNumber 削除対象の品番
     * @return bool 成否
     */
    public function destroy(PartNumber $partNumber): bool
    {
        return $this->partNumber->destroy($partNumber);
    }
}
