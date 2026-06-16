<?php

declare(strict_types=1);

namespace App\Services;

use App\Events\SensorAlarmNotification;
use App\Facades\Slack;
use App\Http\Requests\StoreSensorRequest;
use App\Http\Requests\UpdateSensorRequest;
use App\Models\Sensor;
use App\Repositories\RaspberryPiRepository;
use App\Repositories\SensorEventRepository;
use App\Repositories\SensorRepository;
use Illuminate\Support\Facades\Log;

/**
 * センサーサービス
 */
class SensorService
{
    /**
     * コンストラクタ
     */
    public function __construct(
        private readonly RaspberryPiRepository $raspberryPi,
        private readonly SensorRepository $sensor,
        private readonly SensorEventRepository $sensorEvent,
    ) {}

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
     * @return bool 成否
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
     * @return bool 成否
     */
    public function update(UpdateSensorRequest $request, Sensor $sensor): bool
    {
        return $this->sensor->update($request, $sensor);
    }

    /**
     * センサーを削除する
     *
     * @param Sensor $sensor 削除対象のセンサー
     * @return bool 成否
     */
    public function destroy(Sensor $sensor): bool
    {
        return $this->sensor->destroy($sensor);
    }

    /**
     * センサーイベントを登録する
     *
     * @param int|string $identificationNumber 識別番号
     * @param string $ipAddress IPアドレス
     * @param bool $signal 信号
     * @param int|float $value センサー値
     * @return void
     */
    public function insert(int|string $identificationNumber, string $ipAddress, bool $signal, int|float $value): void
    {
        // MQTTから文字列ピン番号が届くケースを許容し、数値へ正規化する。
        $normalizedIdentificationNumber = $this->normalizeIdentificationNumber($identificationNumber, $ipAddress);
        if (is_null($normalizedIdentificationNumber)) {
            return;
        }

        // 1) IPアドレスでラズパイを特定
        $raspi = $this->raspberryPi->first(['ip_address' => $ipAddress]);
        if (is_null($raspi)) {
            // Log::warning('Raspberry pi not found', ['ip_address' => $ipAddress]);
            return;
        }

        // 2) ラズパイID + 識別番号でセンサー定義を取得
        $sensor = $this->sensor->first([
            'raspberry_pi_id' => $raspi->raspberry_pi_id,
            'identification_number' => $normalizedIdentificationNumber,
        ], 'process');
        if (is_null($sensor)) {
            Log::warning('Sensor not found', [
                'ip_address' => $ipAddress,
                'identification_number' => $normalizedIdentificationNumber,
            ]);
            return;
        }

        // 3) イベント保存に成功した場合のみSlack通知とブロードキャストを実行
        $sensorEvent = $this->sensorEvent->save($sensor, $ipAddress, $signal, $value);
        if (!is_null($sensorEvent)) {
            Slack::send(__($sensorEvent->is_start ? 'yokakit.start_alarm_notification' : 'yokakit.stop_alarm_notification', [
                'process' => $sensor->process->process_name,
                'event' => $sensorEvent->alarm_text,
            ]));
            SensorAlarmNotification::dispatch($sensorEvent->sensor_event_id);
        }
    }

    /**
     * 識別番号を整数へ正規化する
     *
     * MQTT 由来の文字列値も受け入れるが、数値以外はログを出して無視する。
     *
     * @param int|string $identificationNumber
     * @param string $ipAddress
     * @return int|null
     */
    private function normalizeIdentificationNumber(int|string $identificationNumber, string $ipAddress): ?int
    {
        if (is_int($identificationNumber)) {
            return $identificationNumber;
        }

        if (!is_numeric($identificationNumber)) {
            Log::warning('Invalid identification number payload', [
                'ip_address' => $ipAddress,
                'identification_number' => $identificationNumber,
            ]);
            return null;
        }

        return (int) $identificationNumber;
    }
}
