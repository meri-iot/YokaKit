<?php

declare(strict_types=1);

namespace Tests\Unit\Queue;

use App\Queue\OrderedDatabaseConnector;
use App\Queue\OrderedDatabaseQueue;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class OrderedDatabaseConnectorTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    /**
     * OrderedDatabaseQueue を返し、キュー設定をそのまま引き継ぐ。
     */
    public function test_connectはordered_database_queueへ設定を引き継ぐ(): void
    {
        $resolver = Mockery::mock(ConnectionResolverInterface::class);
        $connection = Mockery::mock(Connection::class);
        $resolver
            ->shouldReceive('connection')
            ->once()
            ->with('mysql')
            ->andReturn($connection);

        $connector = new OrderedDatabaseConnector($resolver);

        $queue = $connector->connect([
            'connection' => 'mysql',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
            'after_commit' => true,
        ]);

        $this->assertInstanceOf(OrderedDatabaseQueue::class, $queue);
        $this->assertSame('jobs', $this->readProperty($queue, 'table'));
        $this->assertSame('default', $this->readProperty($queue, 'default'));
        $this->assertSame(90, $this->readProperty($queue, 'retryAfter'));
        $this->assertTrue($this->readProperty($queue, 'dispatchAfterCommit'));
        $this->assertSame($connection, $this->readProperty($queue, 'database'));
    }

    /**
     * 省略された設定には親コネクタと同じ既定値を適用する。
     */
    public function test_connectは省略時に親実装と同じ既定値を使う(): void
    {
        $resolver = Mockery::mock(ConnectionResolverInterface::class);
        $connection = Mockery::mock(Connection::class);
        $resolver
            ->shouldReceive('connection')
            ->once()
            ->with(null)
            ->andReturn($connection);

        $connector = new OrderedDatabaseConnector($resolver);

        $queue = $connector->connect([
            'table' => 'jobs',
            'queue' => 'ordered',
        ]);

        $this->assertSame(60, $this->readProperty($queue, 'retryAfter'));
        $this->assertNull($this->readProperty($queue, 'dispatchAfterCommit'));
    }

    /**
     * 継承元も含めて protected プロパティ値を取り出す。
     */
    private function readProperty(object $target, string $property): mixed
    {
        $reflection = new ReflectionClass($target);

        while (!$reflection->hasProperty($property) && $reflection->getParentClass() !== false) {
            $reflection = $reflection->getParentClass();
        }

        $reflectedProperty = $reflection->getProperty($property);
        $reflectedProperty->setAccessible(true);

        return $reflectedProperty->getValue($target);
    }
}
