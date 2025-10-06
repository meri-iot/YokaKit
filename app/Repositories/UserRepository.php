<?php

namespace App\Repositories;

use App\Enums\RoleType;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

/**
 * ユーザーリポジトリ
 *
 * @extends AbstractRepository<User>
 */
class UserRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string
     */
    public function model(): string
    {
        return User::class;
    }

    /**
     * ユーザーを作成する
     *
     * @param string $name ユーザー名
     * @param string $email ユーザーEメール
     * @param string $password パスワード(生)
     * @param string|int $role 権限
     * @return boolean 成否
     */
    public function create(string $name, string $email, string $password, string|int $role): bool
    {
        $user = new User([
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'password' => Hash::make($password),
        ]);
        return $this->storeModel($user);
    }
}
