<?php

namespace App\Queue;

use Illuminate\Database\Connection;
use Illuminate\Queue\Connectors\DatabaseConnector;

/**
 * キュー管理用のDBコネクタクラス
 *
 * Class CustomDatabaseConnector
 * @package App\Libs
 */
class OrderedDatabaseConnector extends DatabaseConnector
{
    /**
     * returnするクラスを変更したoverride関数
     *
     * @see DatabaseConnector::connect()
     * @param array<string,mixed> $config
     * @return \Illuminate\Contracts\Queue\Queue
     */
    public function connect(array $config)
    {
        /** @var Connection */
        $connection = $this->connections->connection($config['connection'] ?? null);
        return new OrderedDatabaseQueue(
            $connection,
            $config['table'],
            $config['queue'],
            $config['retry_after'] ?? 60
        );
    }
}
