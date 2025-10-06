<?php

namespace App\Services;

use App\Enums\RoleType;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Models\User;
use App\Repositories\UserRepository;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

/**
 * ユーザーサービス
 */
class UserService
{
    private readonly UserRepository $user;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->user = App::make(UserRepository::class);
    }

    /**
     * すべてのユーザーを取得する
     *
     * @return Collection<int,User>
     */
    public function all(): Collection
    {
        return $this->user->all();
    }

    /**
     * プロフィールを更新する
     *
     * @param UpdateProfileRequest $request 作業者更新リクエスト
     * @return boolean 成否
     */
    public function updateProfile(UpdateProfileRequest $request): bool
    {
        return $this->user->update($request, $this->user());
    }

    /**
     * ユーザーを追加する
     *
     * @param StoreUserRequest $request 工程追加リクエスト
     * @return boolean 成否
     */
    public function store(StoreUserRequest $request): bool
    {
        $name = $request->name;
        $email = $request->email;
        $password = $request->password;
        $role = $request->role;
        return $this->user->create($name, $email, $password, $role);
    }

    /**
     * ユーザーを削除する
     *
     * @param User $user 削除対象のユーザー
     * @return boolean 成否
     */
    public function destroy(User $user): bool
    {
        return $this->user->destroy($user);
    }

    /**
     * WebAPI用認証トークンを生成する
     *
     * @return string トークン (平文)
     */
    public function generateToken(): string
    {
        $user = $this->user();
        $user->tokens()->delete();
        $token = $user->createToken(config('app.name'));
        return 'Bearer ' . $token->plainTextToken;
    }

    /**
     * パスワードを更新する
     *
     * @param UpdatePasswordRequest $request 更新リクエスト
     * @return boolean 成否
     */
    public function updatePassword(UpdatePasswordRequest $request): bool
    {
        $user = $this->user();
        $user->password = Hash::make($request->password);
        return $user->save();
    }

    /**
     * ユーザーを作成する
     *
     * @param string $name ユーザー名
     * @param string $email メールアドレス
     * @param string $password パスワード (平文)
     * @param RoleType $role 権限
     * @return boolean 成否
     */
    public function create(string $name, string $email, string $password, RoleType $role): bool
    {
        return $this->user->create($name, $email, $password, $role);
    }

    /**
     * ユーザーを取得する
     *
     * @return User ユーザー
     */
    private function user(): User
    {
        $user = Auth::user();
        if ($user instanceof User) {
            return $user;
        } else {
            throw new AuthorizationException();
        }
    }
}
