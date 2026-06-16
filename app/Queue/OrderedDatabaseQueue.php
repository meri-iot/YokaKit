<?php

declare(strict_types=1);

namespace App\Queue;

use DateTimeInterface;
use Illuminate\Queue\DatabaseQueue;
use Illuminate\Queue\Jobs\DatabaseJobRecord;
use Illuminate\Support\Carbon;

/**
 * 利用可能時刻をミリ秒で扱い、取り出し順を安定化した DB キュー。
 */
class OrderedDatabaseQueue extends DatabaseQueue
{
    /**
     * 未予約かつ実行可能時刻を過ぎたジョブだけを取得対象に含める。
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @return void
     */
    protected function isAvailable($query)
    {
        $query->where(function ($query) {
            $query->whereNull('reserved_at')
                ->where('available_at', '<=', Carbon::now()->getTimestampMs());
        });
    }

    /**
     * 実行可能時刻をミリ秒タイムスタンプへ正規化する。
     *
     * @param  \DateTimeInterface|\DateInterval|int  $delay
     * @return int
     */
    protected function availableAt($delay = 0)
    {
        $delay = $this->parseDateInterval($delay);
        return $delay instanceof DateTimeInterface
            ? (int)$delay->format('Uv')
            : Carbon::now()->addRealMilliseconds($delay)->getTimestampMs();
    }

    /**
     * 次に処理すべきジョブを実行可能時刻順で取得する。
     *
     * 同一時刻のジョブは ID 昇順にして FIFO を保つ。
     *
     * @param  string|null  $queue
     * @return \Illuminate\Queue\Jobs\DatabaseJobRecord|null
     */
    protected function getNextAvailableJob($queue)
    {
        $job = $this->database->table($this->table)
            ->lock($this->getLockForPopping())
            ->where('queue', $this->getQueue($queue))
            ->where(function ($query) {
                $this->isAvailable($query);
                $this->isReservedButExpired($query);
            })
            ->orderBy('available_at', 'asc')
            ->orderBy('id', 'asc')
            ->first();

        return $job ? new DatabaseJobRecord((object) $job) : null;
    }
}
