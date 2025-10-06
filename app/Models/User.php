<?php

namespace App\Models;

use App\Enums\RoleType;
use App\Notifications\PasswordResetNotification;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * ユーザーモデルクラス
 *
 * @property string $name 名前
 * @property string $email メールアドレス
 * @property string $password パスワード
 * @property RoleType $role 権限
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'name',     // 名前
        'email',    // メールアドレス
        'password', // パスワード
        'role',     // 権限
    ];

    /**
     * シリアライズ時に隠す属性
     *
     * @var array<int,string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'email_verified_at',
        'created_at',
        'updated_at',
    ];

    /**
     * キャストする属性
     *
     * @var array<string,string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'role' => RoleType::class,
    ];

    /**
     * AdminLte用のユーザープロファイルURL
     *
     * @return string
     */
    public function adminlte_profile_url(): string
    {
        return 'user/profile';
    }

    /**
     * Send the password reset notification.
     *
     * @param string $token
     * @return void
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new PasswordResetNotification($token));
    }
}
