<?php

namespace App\Services;

use App\Events\OnOffNotificationEvent;
use App\Http\Requests\StoreOnOffRequest;
use App\Http\Requests\UpdateOnOffRequest;
use App\Models\OnOff;
use App\Repositories\OnOffEventRepository;
use App\Repositories\OnOffRepository;
use App\Repositories\RaspberryPiRepository;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * ON-OFFメッセージサービス
 */
class OnOffService
{
    private readonly OnOffRepository $onOff;
    private readonly OnOffEventRepository $onOffEvent;
    private readonly RaspberryPiRepository $raspberryPi;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->onOff = App::make(OnOffRepository::class);
        $this->onOffEvent = App::make(OnOffEventRepository::class);
        $this->raspberryPi = App::make(RaspberryPiRepository::class);
    }

    /**
     * ラズベリーパイ選択用のオプションを取得する
     *
     * @return array<int,string> ラズベリーパイ選択用のオプション
     */
    public function raspberryPiOptions(): array
    {
        return $this->raspberryPi->options();
    }

    /**
     * ON-OFFメッセージを追加する
     *
     * @param StoreOnOffRequest $request ON-OFFメッセージ追加リクエスト
     * @return boolean 成否
     */
    public function store(StoreOnOffRequest $request): bool
    {
        return $this->onOff->store($request);
    }

    /**
     * ON-OFFメッセージを更新する
     *
     * @param UpdateOnOffRequest $request ON-OFFメッセージ更新リクエスト
     * @param OnOff $onOff 更新対象のON-OFFメッセージ
     * @return boolean 成否
     */
    public function update(UpdateOnOffRequest $request, OnOff $onOff): bool
    {
        return $this->onOff->update($request, $onOff);
    }

    /**
     * ON-OFFメッセージを削除する
     *
     * @param OnOff $onOff 削除対象のON-OFFメッセージ
     * @return boolean 成否
     */
    public function destroy(OnOff $onOff): bool
    {
        return $this->onOff->destroy($onOff);
    }

    /**
     * ON-OFFメッセージイベントを登録する
     *
     * @param boolean $isOn trueならON
     * @param integer $pinNumber ピン番号
     * @param string $ipAddress IPアドレス
     * @return boolean 成否
     */
    public function insert(bool $isOn, int $pinNumber, string $ipAddress): bool
    {
        // IPアドレスからラズパイを検索
        $raspi = $this->raspberryPi->first(['ip_address' => $ipAddress]);
        if (is_null($raspi)) {
            // Log::warning('Raspberry pi not found', [$ipAddress]);
            return false;
        }
        // ラズパイIDとピン番号からON-OFFメッセージ設定を取得
        $onOff = $this->onOff->first([
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'pin_number' => $pinNumber,
        ]);
        if (is_null($onOff)) {
            Log::warning('On off message not found', [$pinNumber]);
            return false;
        }
        // ON-OFFメッセージイベントを登録する
        $event = $this->onOffEvent->save($onOff, $isOn);
        if (!is_null($event) && !is_null($event->message)) {
            OnOffNotificationEvent::dispatch($event->on_off_event_id);
            return true;
        } else {
            return false;
        }
    }
}
