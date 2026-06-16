<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Models\Process;
use App\Services\AndonService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

/**
 * アンドン情報を取得するAPIコントローラークラス
 */
class AndonInfoController extends BaseController
{
    /**
     * コンストラクタ
     *
     * @param AndonService $service アンドンサービス
     */
    public function __construct(private readonly AndonService $service) {}

    /**
     * 受信したリクエストを処理し、アンドン表示用の指標一覧を返す。
     *
     * 管理者権限を確認したうえで、AndonService から工程指標を取得する。
     *
     * @param Request $request
     * @return Collection<int,Process>
     */
    public function __invoke(Request $request): Collection
    {
        $this->authorizeAdmin();

        return $this->service->indicators();
    }
}
