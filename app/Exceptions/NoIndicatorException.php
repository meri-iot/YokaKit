<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

/**
 * 生産時の指標が設定されていない例外
 */
class NoIndicatorException extends Exception
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
