<?php

namespace App\Events;

use App\Data\PayloadData;
use App\Models\ProductionHistory;
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
     * @param ProductionHistory $history
     * @param PayloadData $payloadData
     */
    public function __construct(ProductionHistory $history, PayloadData $payloadData)
    {
        $this->data = $history->makeProductionSummary($payloadData);
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
