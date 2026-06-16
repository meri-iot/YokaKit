<?php

declare(strict_types=1);

namespace App\Events;

use App\Data\ProductionSummaryData;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * 生産サマリー通知用ブロードキャスト送信クラス
 */
class ProductionSummaryNotification implements ShouldBroadcast
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
     * @param ProductionSummaryData $data ブロードキャスト送信データ
     */
    public function __construct(ProductionSummaryData $data)
    {
        $this->data = $data->toArray();
        Log::debug('Dispatch ProductionSummaryNotification', $this->data);
    }

    /**
     * イベントをブロードキャストするチャンネルを取得します。
     *
     * @return Channel|array<int,Channel>|array<int,string>
     */
    public function broadcastOn(): Channel|array
    {
        return new PresenceChannel('summary');
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
