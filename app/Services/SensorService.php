<?php

namespace App\Services;

use App\Events\SensorAlarmNotification;
use App\Facades\Slack;
use App\Http\Requests\StoreSensorRequest;
use App\Http\Requests\UpdateSensorRequest;
use App\Models\Sensor;
use App\Repositories\RaspberryPiRepository;
use App\Repositories\SensorEventRepository;
use App\Repositories\SensorRepository;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Log;

/**
 * センサーサービス
 */
class SensorService
{
    private readonly RaspberryPiRepository $raspberryPi;
    private readonly SensorRepository $sensor;
    private readonly SensorEventRepository $sensorEvent;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->raspberryPi = App::make(RaspberryPiRepository::class);
        $this->sensor = App::make(SensorRepository::class);
        $this->sensorEvent = App::make(SensorEventRepository::class);
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
     * センサーを追加する
     *
     * @param StoreSensorRequest $request センサー追加リクエスト
     * @return boolean 成否
     */
    public function store(StoreSensorRequest $request): bool
    {
        return $this->sensor->store($request);
    }

    /**
     * センサーを更新する
     *
     * @param UpdateSensorRequest $request センサー更新リクエスト
     * @param Sensor $sensor 更新対象のセンサー
     * @return boolean 成否
     */
    public function update(UpdateSensorRequest $request, Sensor $sensor): bool
    {
        return $this->sensor->update($request, $sensor);
    }

    /**
     * センサーを削除する
     *
     * @param Sensor $sensor 削除対象のセンサー
     * @return boolean 成否
     */
    public function destroy(Sensor $sensor): bool
    {
        return $this->sensor->destroy($sensor);
    }

    /**
     * センサーイベントを登録する
     *
     * @param int $identificationNumber 識別番号
     * @param string $ipAddress IPアドレス
     * @param boolean $signal 信号
     * @param integer|float $value センサー値
     * @return void
     */
    public function insert(int $identificationNumber, string $ipAddress, bool $signal, int|float $value): void
    {
        $raspi = $this->raspberryPi->first(['ip_address' => $ipAddress]);
        if (is_null($raspi)) {
            // Log::warning('Raspberry pi not found', [$ipAddress]);
            return;
        }
        $sensor = $this->sensor->first([
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'identification_number' => $identificationNumber,
        ], 'process');
        if (is_null($sensor)) {
            Log::warning('Sensor not found', [$identificationNumber]);
            return;
        }
        $sensorEvent = $this->sensorEvent->save($sensor, $ipAddress, $signal, $value);
        if (!is_null($sensorEvent)) {
            Slack::send(__($sensorEvent->is_start ? 'yokakit.start_alarm_notification' : 'yokakit.stop_alarm_notification', [
                'process' => $sensor->process->process_name,
                'event' => $sensorEvent->alarm_text,
            ]));
            SensorAlarmNotification::dispatch($sensorEvent->sensor_event_id);
        }
    }
}
