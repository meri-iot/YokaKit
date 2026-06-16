<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * 生産カウントの通知エラー
 */
class ProductionException extends Exception
{
    /**
     * Create a new exception instance.
     *
     * @param string $message メッセージ
     */
    public function __construct(string $message = '')
    {
        parent::__construct($message);
    }
}
