<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;

/**
 * モデルのコントローラー抽象クラス
 */
abstract class AbstractController extends BaseController
{
    /**
     * UI表示用名称を取得する
     *
     * @return string 名称
     */
    abstract public function name(): string;

    /**
     * モデル作成後のページリダイレクト
     *
     * @param boolean $result 成功 or 失敗
     * @param string $path リダイレクトパス
     * @param array<string,mixed> $parameters リダイレクトパラメータ
     * @return RedirectResponse
     */
    protected function redirectWithStore(bool $result, string $path, array $parameters = []): RedirectResponse
    {
        return $this->redirect($result, __('yokakit.register'), $path, $parameters);
    }

    /**
     * モデル更新後のページリダイレクト
     *
     * @param boolean $result 成功 or 失敗
     * @param string $path リダイレクトパス
     * @param array<string,mixed> $parameters リダイレクトパラメータ
     * @return RedirectResponse
     */
    protected function redirectWithUpdate(bool $result, string $path, array $parameters = []): RedirectResponse
    {
        return $this->redirect($result, __('yokakit.update'), $path, $parameters);
    }

    /**
     * モデル削除後のページリダイレクト
     *
     * @param boolean $result 成功 or 失敗
     * @param string $path リダイレクトパス
     * @param array<string,mixed> $parameters リダイレクトパラメータ
     * @return RedirectResponse
     */
    protected function redirectWithDestroy(bool $result, string $path, array $parameters = []): RedirectResponse
    {
        return $this->redirect($result, __('yokakit.delete'), $path, $parameters);
    }

    /**
     * モデルのページリダイレクト
     *
     * @param boolean $result 成功 or 失敗
     * @param string $action 作業名称
     * @param string $path リダイレクトパス
     * @param array<string,mixed> $parameters リダイレクトパラメータ
     * @return RedirectResponse
     */
    protected function redirect(bool $result, string $action, string $path, array $parameters = []): RedirectResponse
    {
        $route = redirect()->route($path, $parameters);
        if ($result) {
            $route->with('toast_success', __('yokakit.success_toast', ['target' => $this->name(), 'action' => $action]));
        } else {
            $route->with('toast_danger', __('yokakit.failed_toast', ['target' => $this->name(), 'action' => $action]));
        }
        return $route;
    }
}
