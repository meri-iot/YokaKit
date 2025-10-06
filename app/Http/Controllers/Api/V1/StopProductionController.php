<?php

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
     * Handle the incoming request.
     *
     * @param StopProductionRequest $request
     * @return Response
     */
    public function __invoke(StopProductionRequest $request)
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
