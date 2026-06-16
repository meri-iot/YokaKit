<?php

declare(strict_types=1);

namespace App\Queue;

use Illuminate\Contracts\Queue\Queue as QueueContract;
use Illuminate\Database\Connection;
use Illuminate\Queue\Connectors\DatabaseConnector;

/**
 * 利用可能時刻順で取り出すカスタム DB キューを生成するコネクタ。
 */
class OrderedDatabaseConnector extends DatabaseConnector
{
    /**
     * アプリ設定から OrderedDatabaseQueue を構築する。
     *
     * @param array<string,mixed> $config
     * @return QueueContract
     */
    public function connect(array $config): QueueContract
    {
        /** @var Connection */
        $connection = $this->connections->connection($config['connection'] ?? null);

        return new OrderedDatabaseQueue(
            $connection,
            $config['table'],
            $config['queue'],
            $config['retry_after'] ?? 60,
            $config['after_commit'] ?? null,
        );
    }
}
