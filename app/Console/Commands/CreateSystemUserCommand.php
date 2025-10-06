<?php

namespace App\Console\Commands;

use App\Enums\RoleType;
use App\Services\UserService;
use Cerbero\CommandValidator\ValidatesInput;
use Illuminate\Console\Command;
use Illuminate\Validation\Rules\Password;

/**
 * システムユーザー作成コマンドクラス
 */
class CreateSystemUserCommand extends Command
{
    use ValidatesInput;

    /**
     * コマンドの名前と引数の説明
     *
     * @var string
     */
    protected $signature = 'make:user {name : The name of the user} {email : The email address of the user} {password : The password of the user}';

    /**
     * コマンドの説明
     *
     * @var string
     */
    protected $description = 'Create a new system role user';

    /**
     * コンストラクタ
     *
     * @param UserService $userService ユーザーサービス
     * @return void
     */
    public function __construct(private readonly UserService $userService)
    {
        parent::__construct();
    }

    /**
     * コマンドの引数に適用するバリデーションルール
     *
     * @return array<string,mixed>
     */
    protected function rules(): array
    {
        return [
            'name' => 'required|string|min:1|max:255',
            'email' => 'required|string|min:3|max:255|email|unique:users,email',
            'password' => ['required', 'string', Password::min(8)],
        ];
    }

    /**
     * コマンドを実行する
     *
     * @return int
     */
    public function handle(): int
    {
        $name = $this->argument('name');
        $email = $this->argument('email');
        $password = $this->argument('password');
        if ($this->userService->create($name, $email, $password, RoleType::SYSTEM())) {
            return Command::SUCCESS;
        } else {
            return Command::FAILURE;
        }
    }
}
