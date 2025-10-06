<?php

namespace App\Repositories;

use App\Models\OnOff;
use App\Models\OnOffEvent;
use Illuminate\Support\Facades\Log;

/**
 * ON-OFFメッセージイベントリポジトリ
 *
 * @extends AbstractRepository<OnOffEvent>
 */
class OnOffEventRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string
     */
    public function model(): string
    {
        return OnOffEvent::class;
    }

    /**
     * ON-OFFメッセージイベントを追加する
     *
     * @param OnOff $onOff ON-OFFメッセージ
     * @param boolean $isOn ON or OFF
     * @return OnOffEvent|null 追加されたON-OFFメッセージイベント (失敗時はnull)
     */
    public function save(OnOff $onOff, bool $isOn): ?OnOffEvent
    {
        $o = new OnOffEvent([
            'process_id' => $onOff->process_id,
            'on_off_id' => $onOff->on_off_id,
            'event_name' => $onOff->event_name,
            'message' => $isOn ? $onOff->on_message : $onOff->off_message,
            'on_off' => $isOn,
            'pin_number' => $onOff->pin_number,
        ]);
        return $this->storeModel($o) ? $o : null;
    }
}
