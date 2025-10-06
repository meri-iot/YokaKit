<?php

namespace App\Events;

use App\Repositories\SensorEventRepository;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\App;

/**
 * センサーアラーム通知のブロードキャスト送信クラス
 */
class SensorAlarmNotification implements ShouldBroadcast
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
     * @param integer $sensorEventId センサーイベントID
     */
    public function __construct(int $sensorEventId)
    {
        /** @var SensorEventRepository */
        $repository = App::make(SensorEventRepository::class);
        $this->data = $repository->find($sensorEventId)->toArray();
    }

    /**
     * イベントをブロードキャストするチャンネルを取得します。
     *
     * @return Channel|array<int,Channel>|array<int,string>
     */
    public function broadcastOn(): Channel|array
    {
        return new PresenceChannel('alarm');
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
