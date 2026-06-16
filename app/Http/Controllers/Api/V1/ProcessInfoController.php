<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Services\ProcessService;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * 工程情報を取得するAPIコントローラークラス
 */
class ProcessInfoController extends BaseController
{
    /**
     * コンストラクタ
     *
     * @param ProcessService $service 工程サービス
     */
    public function __construct(private readonly ProcessService $service) {}

    /**
     * 受信したリクエストを処理し、工程情報一覧を返す。
     *
     * 管理者権限を確認したうえで、ProcessService から工程情報を取得する。
     *
     * @param Request $request
     * @return Collection<int,array<string,mixed>>
     */
    public function __invoke(Request $request): Collection
    {
        $this->authorizeAdmin();

        return $this->service->allProcessInfo();
    }
}
