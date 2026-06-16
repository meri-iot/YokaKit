<?php

declare(strict_types=1);

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
     * @return class-string<OnOffEvent>
     */
    public function model(): string
    {
        return OnOffEvent::class;
    }

    /**
     * ON-OFFメッセージイベントを新規保存する
     *
     * $isOn が true の場合は on_message を、false の場合は off_message を
     * メッセージとして保存する。off_message は nullable であるため、
     * OFF 時でも message が null になることがある。
     *
     * 保存後の $event インスタンスには PK (on_off_event_id) が設定される。
     *
     * @param OnOff $onOff ON-OFFメッセージ設定
     * @param bool  $isOn  true なら ON イベント、false なら OFF イベント
     * @return OnOffEvent|null 保存したイベント。保存失敗時は null
     */
    public function save(OnOff $onOff, bool $isOn): ?OnOffEvent
    {
        // ON/OFF フラグに応じてメッセージを選択する。
        $event = new OnOffEvent([
            'process_id' => $onOff->process_id,
            'on_off_id' => $onOff->on_off_id,
            'event_name' => $onOff->event_name,
            'message' => $isOn ? $onOff->on_message : $onOff->off_message,
            'on_off' => $isOn,
            'pin_number' => $onOff->pin_number,
        ]);
        return $this->storeModel($event) ? $event : null;
    }
}
