<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\GanttChartEvent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * ガントチャート通知のブロードキャスト送信クラス
 */
class GanttChartNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * ブロードキャスト送信データ
     *
     * @var array<string,mixed>
     */
    private array $data;

    /**
     * イベントインスタンスを作成します。
     *
     * @param GanttChartEvent $event イベント
     */
    public function __construct(GanttChartEvent $event)
    {
        Log::debug("message", $event->toArray());
        $this->data = $event->toArray();
    }

    /**
     * イベントをブロードキャストするチャンネルを取得します。
     *
     * @return Channel|array<int,Channel>|array<int,string>
     */
    public function broadcastOn(): Channel|array
    {
        return new PresenceChannel('gantt-chart');
    }

    /**
     * ブロードキャストのデータを取得します。
     *
     * @return array<string,mixed>
     */
    public function broadcastWith(): array
    {
        return $this->data;
    }
}
