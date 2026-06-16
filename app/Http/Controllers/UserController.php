<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleType;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Services\UserService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * ユーザーコントローラー
 */
class UserController extends AbstractController
{
    /**
     * コンストラクタ
     *
     * @param UserService $service
     */
    public function __construct(private readonly UserService $service)
    {
        $this->middleware('auth');
    }

    /**
     * UI表示用名称を取得する
     *
     * @return string 名称
     */
    public function name(): string
    {
        return __('yokakit.user');
    }

    /**
     * ユーザー一覧画面を表示する。システム管理者のみ使用可能。
     *
     * @return View
     */
    public function index(): View
    {
        $this->authorizeSystem();
        $users = $this->service->all();
        return view('user.index', ['users' => $users]);
    }

    /**
     * ユーザー追加フォーム画面を表示する。システム管理者のみ使用可能。
     *
     * @return View
     */
    public function create(): View
    {
        $this->authorizeSystem();
        $roles = array_combine(RoleType::getValues(), array_map(fn($x) => $x->description, RoleType::getInstances()));
        return view('user.create', ['roles' => $roles]);
    }

    /**
     * ユーザーを新規登録する。システム管理者のみ使用可能。
     *
     * @param StoreUserRequest $request
     * @return RedirectResponse
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $this->authorizeSystem();
        $result = $this->service->store($request);
        return $this->redirectWithStore($result, 'user.index');
    }

    /**
     * 自分のプロフィール画面を表示する。
     *
     * @return View
     */
    public function show(): View
    {
        return view('user.show', ['user' => Auth::user()]);
    }

    /**
     * 自分のプロフィール編集画面を表示する。
     *
     * @return View
     */
    public function edit(): View
    {
        return view('user.edit', ['user' => Auth::user()]);
    }

    /**
     * ユーザーを削除する。システム管理者のみ使用可能。
     *
     * @param User $user
     * @return RedirectResponse
     */
    public function destroy(User $user): RedirectResponse
    {
        $this->authorizeSystem();
        $result = $this->service->destroy($user);
        if ($result && $user->is(Auth::user())) {
            Auth::logout();
            return redirect()->route('home');
        } else {
            return $this->redirectWithDestroy($result, 'user.index');
        }
    }

    /**
     * 自分のプロフィールを更新する。
     *
     * @param UpdateProfileRequest $request
     * @return RedirectResponse
     */
    public function profile(UpdateProfileRequest $request): RedirectResponse
    {
        $result = $this->service->updateProfile($request);
        $route = redirect()->route('user.show', ['user' => Auth::user()]);
        if ($result) {
            $route->with('toast_success', __('yokakit.success_toast', ['target' => __('yokakit.profile'), 'action' => __('yokakit.update')]));
        } else {
            $route->with('toast_danger', __('yokakit.failed_toast', ['target' => __('yokakit.profile'), 'action' => __('yokakit.update')]));
        }
        return $route;
    }

    /**
     * トークン生成
     *
     * @return RedirectResponse
     */
    public function token(): RedirectResponse
    {
        $this->authorizeAdmin();
        $token = $this->service->generateToken();
        return redirect()->route('user.show')->with('token', $token);
    }

    /**
     * パスワード変更画面
     *
     * @return View
     */
    public function password(): View
    {
        return view('user.password', ['user' => Auth::user()]);
    }

    /**
     * パスワード変更処理
     *
     * @param UpdatePasswordRequest $request
     * @return RedirectResponse
     */
    public function change(UpdatePasswordRequest $request): RedirectResponse
    {
        $result = $this->service->updatePassword($request);
        $route = redirect()->route('user.show', ['user' => Auth::user()]);
        if ($result) {
            $route->with('toast_success', __('yokakit.success_toast2', ['action' => __('yokakit.change_password')]));
        } else {
            $route->with('toast_danger', __('yokakit.failed_toast2', ['action' => __('yokakit.change_password')]));
        }
        return $route;
    }
}
