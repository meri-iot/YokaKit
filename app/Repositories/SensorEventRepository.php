<?php

namespace App\Repositories;

use App\Models\Sensor;
use App\Models\SensorEvent;
use Illuminate\Support\Facades\Log;

/**
 * センサーイベントリポジトリ
 *
 * @extends AbstractRepository<SensorEvent>
 */
class SensorEventRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string
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
     * @param boolean $signal ON-OFF信号
     * @param integer|float $value センサー値
     * @return SensorEvent|null 登録されたイベント (失敗時はnull)
     */
    public function save(Sensor $sensor, string $ipAddress, bool $signal, int|float $value): ?SensorEvent
    {
        $s = new SensorEvent([
            'process_id' => $sensor->process_id,
            'sensor_id' => $sensor->sensor_id,
            'ip_address' => $ipAddress,
            'identification_number' => $sensor->identification_number,
            'alarm_text' => $sensor->alarm_text,
            'trigger' => $sensor->trigger,
            'signal' => $signal,
            'value' => $value,
        ]);

        if ($this->storeModel($s)) {
            return $s;
        } else {
            return null;
        }
    }
}
