<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\SlackMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * スラック通知クラス
 *
 * @see https://qiita.com/freeneer/items/aa82a0c49842b7c379ec
 */
class SlackNotification extends Notification
{
    use Queueable;

    /**
     * スラック通知ユーザー名
     *
     * @var string
     */
    protected string $username;

    /**
     * スラック通知アイコン
     *
     * @var string
     */
    protected string $icon;

    /**
     * スラック通知チャンネル
     *
     * @var string
     */
    protected string $channel;

    /**
     * プロキシサーバーURL
     *
     * @var string
     */
    protected string $proxy;

    /**
     * Create a new notification instance.
     *
     * @param string|null $message 通知メッセージ
     */
    public function __construct(protected ?string $message = null)
    {
        $this->username = config('slack.username');
        $this->icon = config('slack.icon');
        $this->channel = config('slack.channel');
        $this->proxy = config('slack.proxy.https');
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array<int,string>
     */
    public function via($notifiable): array
    {
        return ['slack'];
    }

    /**
     * Slack通知表現を返す
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\SlackMessage
     */
    public function toSlack($notifiable)
    {
        // スラック通知の構築
        $msg = (new SlackMessage)
            ->from($this->username, $this->icon)
            ->to($this->channel)
            ->content($this->message);

        // プロキシサーバーの指定
        if ($this->proxy) {
            $msg->http(['proxy' => ['https' => $this->proxy]]);
        }

        // ログ出力
        $log = [
            'Username' => $this->username,
            'Icon' => $this->icon,
            'Channel' => $this->channel,
            'Proxy' => $this->proxy
        ];
        Log::debug("Send slack: $this->message " . json_encode($log));

        return $msg;
    }

    /**
     * Get the array representation of the notification.
     *
     * @param mixed $notifiable
     * @return array<int,mixed>
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }
}
