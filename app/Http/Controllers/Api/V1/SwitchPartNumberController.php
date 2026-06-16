<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\NoIndicatorException;
use App\Http\Controllers\BaseController;
use App\Http\Requests\SwitchPartNumberRequestFromApi;
use App\Services\ProductionHistoryService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * 品番切り替えを行うAPIコントローラークラス
 */
class SwitchPartNumberController extends BaseController
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
     * 受信したリクエストを処理し、品番切り替えを実行する。
     *
     * 管理者権限を確認したうえで切り替え処理を呼び出し、切り替え不可または
     * 対象データ不整合時には適切なHTTPエラーを返す。
     *
     * @param SwitchPartNumberRequestFromApi $request
     * @return Response
     */
    public function __invoke(SwitchPartNumberRequestFromApi $request): Response
    {
        $this->authorizeAdmin();
        try {
            $result = $this->service->switchPartNumberFromApi($request);
            if (!$result) {
                $response = response(status: 400);
                throw new HttpResponseException($response);
            }
            return new Response();
        } catch (ModelNotFoundException $th) {
            Log::error($th->getMessage(), $th->getTrace());
            $response = response(status: 400);
            throw new HttpResponseException($response);
        } catch (NoIndicatorException $th) {
            Log::error($th->getMessage(), $th->getTrace());
            $response = response()->json(['errors' => [__(
                'yokakit.not_exists_toast',
                [
                    'target' => __('yokakit.indicator'),
                    'action' => __('yokakit.reset'),
                ]
            )]], 500);
            throw new HttpResponseException($response);
        }
    }
}
