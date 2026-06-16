<?php

namespace Tests\Feature\Slack;

use App\Facades\Slack;
use App\Services\SlackService;
use App\Notifications\SlackNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * スラック通知テスト
 *
 * @see https://takuma-it.com/slack-notification/
 */
class SlackTest extends TestCase
{
    /**
     * A basic feature test example.
     *
     * @return void
     */
    public function test_スラック通知成功()
    {
        $expected_message = 'test message';
        $expected_icon = ':oden:';
        $expected_username = 'YokaKit';
        $expected_proxy = 'http://10.0.0.2:8080';

        // Slack通知が実際に送信されないようにする
        Notification::fake();
        // 送信されていないことを確認
        Notification::assertNothingSent();
        // 送信
        Slack::send($expected_message);
        // Slack通知の送信回数が1回かのテスト(第３引数の数値が期待する送信回数)
        Notification::assertSentToTimes(new SlackService, SlackNotification::class, 1);
        // Slack通知に記載されている内容をテスト
        Notification::assertSentTo(
            new SlackService,
            SlackNotification::class,
            // 第一引数の$notificationはSlackNotification::classのインスタンス
            function ($notification, $channels) use ($expected_message, $expected_icon, $expected_username, $expected_proxy) {
                // ReflectionClassのインスタンスを作成
                $reflected_notification = new \ReflectionClass($notification);
                // $notificationのmessageプロパティを取得
                $message_property = $reflected_notification->getProperty('message');
                $icon_property = $reflected_notification->getProperty('icon');
                $username_property = $reflected_notification->getProperty('username');
                $proxy_property = $reflected_notification->getProperty('proxy');
                // messageプロパティへのアクセスを許可
                $message_property->setAccessible(true);
                $icon_property->setAccessible(true);
                $username_property->setAccessible(true);
                $proxy_property->setAccessible(true);
                // messageプロパティの値(Slack通知に記載されている内容)を取得する
                $message = $message_property->getValue($notification);
                $icon = $icon_property->getValue($notification);
                $username = $username_property->getValue($notification);
                $proxy = $proxy_property->getValue($notification);
                // messageプロパティの値が期待するメッセージと同じかをテストする
                $this->assertEquals($expected_message, $message);
                $this->assertEquals($expected_icon, $icon);
                $this->assertEquals($expected_username, $username);
                $this->assertEquals($expected_proxy, $proxy);
                // 最後にtrueを返さないとエラーになる
                return true;
            }
        );
    }
}
