<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Sensor;
use App\Models\SensorEvent;

/**
 * センサーイベントリポジトリ
 *
 * @extends AbstractRepository<SensorEvent>
 */
class SensorEventRepository extends AbstractRepository
{
    /**
     * このリポジトリが扱うモデルクラスを返す
     *
     * @return class-string<SensorEvent>
     */
    public function model(): string
    {
        return SensorEvent::class;
    }

    /**
     * センサーイベントを登録する
     *
     * @param Sensor $sensor センサー
     * @param string $ipAddress IPアドレス
     * @param bool $signal ON-OFF信号
     * @param int|float $value センサー値
     * @return SensorEvent|null 登録されたイベント (失敗時はnull)
     */
    public function save(Sensor $sensor, string $ipAddress, bool $signal, int|float $value): ?SensorEvent
    {
        // センサー定義の固定情報を引き継ぎつつ、通知時点の信号と値を保存する。
        $event = new SensorEvent([
            'process_id' => $sensor->process_id,
            'sensor_id' => $sensor->sensor_id,
            'ip_address' => $ipAddress,
            'identification_number' => $sensor->identification_number,
            'alarm_text' => $sensor->alarm_text,
            'trigger' => $sensor->trigger,
            'signal' => $signal,
            'value' => $value,
        ]);
        return $this->storeModel($event) ? $event : null;
    }
}
