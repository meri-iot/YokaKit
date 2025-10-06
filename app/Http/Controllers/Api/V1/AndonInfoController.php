<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Services\AndonService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * アンドン情報を取得するAPIコントローラークラス
 */
class AndonInfoController extends BaseController
{
    /**
     * コンストラクタ
     *
     * @param AndonService $service 工程サービス
     */
    public function __construct(private readonly AndonService $service) {}

    /**
     * 受信したリクエストを処理し、ProcessService を使用して全ての工程情報を取得し、不要なフィールドを除いた工程データを返します。
     *
     * @param Request $request
     * @return Collection<int, array<string,mixed>>
     */
    public function __invoke(Request $request): Collection
    {
        return $this->service->indicators();
    }
}
