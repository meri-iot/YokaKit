<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\BaseController;
use App\Http\Requests\StopProductionRequest;
use App\Services\ProductionHistoryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * 生産の停止を行うAPIコントローラークラス
 */
class StopProductionController extends BaseController
{
    /**
     * コンストラクタ
     *
     * @param ProductionHistoryService $service 生産履歴サービス
     */
    public function __construct(
        private readonly ProductionHistoryService $service
    ) {}

    /**
     * 受信したリクエストを処理し、生産停止を実行する。
     *
     * 管理者権限を確認したうえで停止処理を呼び出し、対象工程が見つからない場合は
     * 400 Bad Request を返す。
     *
     * @param StopProductionRequest $request
     * @return Response
     */
    public function __invoke(StopProductionRequest $request): Response
    {
        $this->authorizeAdmin();
        try {
            $this->service->stopFromApi($request);
            return new Response();
        } catch (ModelNotFoundException $th) {
            Log::error($th->getMessage(), $th->getTrace());
            $response = response(status: 400);
            throw new HttpResponseException($response);
        }
    }
}
