<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Process;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;

/**
 * 基底コントローラークラス
 */
abstract class BaseController extends Controller
{
    use AuthorizesRequests, DispatchesJobs, ValidatesRequests;

    /**
     * 管理者権限のみ可
     *
     * @return void
     * @throws AuthorizationException 管理者権限がない場合に例外を投げる
     */
    protected function authorizeAdmin(): void
    {
        $this->authorize('admin');
    }

    /**
     * システム管理者権限のみ可
     *
     * @return void
     * @throws AuthorizationException システム管理者権限がない場合に例外を投げる
     */
    protected function authorizeSystem(): void
    {
        $this->authorize('system');
    }

    /**
     * 指定した工程が停止していれば可
     *
     * 稼働中の工程に対して停止中前提の処理が呼ばれないようにガードする。
     *
     * @param Process $process 工程
     * @return void
     * @throws AuthorizationException 工程が実行中の場合に例外を投げる
     */
    protected function throwExceptionIfRunning(Process $process): void
    {
        if ($process->isRunning()) {
            throw new AuthorizationException();
        }
    }
}
