<?php

declare(strict_types=1);

namespace App\Facades;

use App\Services\SlackService;
use Illuminate\Support\Facades\Facade;

/**
 * スラック通知用ファサード
 *
 * @method static void send(string $message) スラックへの通知を送信
 */
class Slack extends Facade
{
    /**
     * ファサードアクセサ
     *
     * @return class-string
     */
    protected static function getFacadeAccessor()
    {
        return SlackService::class;
    }
}
