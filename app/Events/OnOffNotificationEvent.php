<?php

namespace App\Events;

use App\Repositories\OnOffEventRepository;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

/**
 * ON-OFFメッセージ通知のブロードキャスト送信クラス
 */
class OnOffNotificationEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * ブロードキャスト送信データ
     *
     * @var array<string,mixed>
     */
    private array $data;

    /**
     * イベントインスタンスの生成します。
     *
     * @param integer $onOffEventId ON-OFFイベントID
     */
    public function __construct(int $onOffEventId)
    {
        /** @var OnOffEventRepository */
        $repository = App::make(OnOffEventRepository::class);
        $this->data = $repository->find($onOffEventId)?->toArray() ?? [];
    }

    /**
     * イベントがブロードキャストされるチャンネルを取得します。
     *
     * @return Channel|array<int,Channel>|array<int,string>
     */
    public function broadcastOn(): Channel|array
    {
        return new PresenceChannel('onoff');
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
