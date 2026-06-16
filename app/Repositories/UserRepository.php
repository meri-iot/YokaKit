<?php

declare(strict_types=1);

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
     * このリポジトリが扱うモデルクラスを返す
     *
     * @return class-string<User>
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
     * @param RoleType|string|int $role 権限
     * @return bool 成否
     */
    public function create(string $name, string $email, string $password, RoleType|string|int $role): bool
    {
        $roleValue = $role instanceof RoleType ? $role->value : $role;

        $user = new User([
            'name' => $name,
            'email' => $email,
            'role' => $roleValue,
            'password' => Hash::make($password),
        ]);
        return $this->storeModel($user);
    }
}
