<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Exceptions\ProductionException;
use App\Services\BarcodeHistoryService;
use App\Services\GanttChartService;
use App\Services\OnOffService;
use App\Services\ProductionHistoryService;
use App\Services\ProductionService;
use App\Services\RaspberryPiService;
use App\Services\SensorService;
use App\Services\Utility;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use PhpMqtt\Client\Facades\MQTT;
use Throwable;

/**
 * MQTTサブスクライブコマンドクラス
 */
class MqttSubscribeCommand extends Command
{
    /**
     * コマンドの名前と引数の説明
     *
     * @var string
     */
    protected $signature = 'mqtt:subscribe';

    /**
     * コマンドの説明
     *
     * @var string
     */
    protected $description = 'Start MQTT subscription';

    /**
     * コンストラクタ
     *
     * @param RaspberryPiService $raspberryPiService
     * @param ProductionService $productionService
     * @param BarcodeHistoryService $barcodeHistoryService
     * @param ProductionHistoryService $productionHistoryService
     * @param SensorService $sensorService
     * @param OnOffService $onOffService
     * @param GanttChartService $ganttChartService
     */
    public function __construct(
        private readonly RaspberryPiService $raspberryPiService,
        private readonly ProductionService $productionService,
        private readonly BarcodeHistoryService $barcodeHistoryService,
        private readonly ProductionHistoryService $productionHistoryService,
        private readonly SensorService $sensorService,
        private readonly OnOffService $onOffService,
        private readonly GanttChartService $ganttChartService
    ) {
        parent::__construct();
    }

    /**
     * コマンドを実行する
     *
     * @return int
     */
    public function handle(): int
    {
        $mqtt = MQTT::connection();
        try {
            // 不正なJSONを受信しても購読処理全体が停止しないように、型チェックしてから各ハンドラへ渡す。
            $mqtt->subscribe('heartbeat', function ($_topic, $message) {
                $payload = $this->decodePayload($message, 'heartbeat');
                if (!is_null($payload)) {
                    $this->subscribeHeartbeat($payload);
                }
            }, 1);
            $mqtt->subscribe('production', function ($_topic, $message) {
                $payload = $this->decodePayload($message, 'production');
                if (!is_null($payload)) {
                    $this->subscribeProduction($payload);
                }
            }, 2);
            $mqtt->subscribe('barcode', function ($_topic, $message) {
                $payload = $this->decodePayload($message, 'barcode');
                if (!is_null($payload)) {
                    $this->subscribeBarcode($payload);
                }
            }, 2);
            $mqtt->subscribe('alarm', function ($_topic, $message) {
                $payload = $this->decodePayload($message, 'alarm');
                if (!is_null($payload)) {
                    $this->subscribeAlarm($payload);
                }
            }, 2);
            $mqtt->subscribe('onoff', function ($_topic, $message) {
                $payload = $this->decodePayload($message, 'onoff');
                if (!is_null($payload)) {
                    $this->subscribeOnOff($payload);
                }
            }, 2);
            $mqtt->subscribe('gantt-chart', function ($_topic, $message) {
                $payload = $this->decodePayload($message, 'gantt-chart');
                if (!is_null($payload)) {
                    $this->subscribeGanttChart($payload);
                }
            }, 2);
            $mqtt->loop(true);
            return Command::SUCCESS;
        } catch (Throwable $e) {
            Log::error($e->getMessage(), $e->getTrace());
            return Command::FAILURE;
        } finally {
            $mqtt->unsubscribe('production');
            $mqtt->unsubscribe('heartbeat');
            $mqtt->unsubscribe('barcode');
            $mqtt->unsubscribe('alarm');
            $mqtt->unsubscribe('onoff');
            $mqtt->unsubscribe('gantt-chart');
            $mqtt->disconnect();
        }
    }

    /**
     * 受信ペイロードを連想配列へ変換する
     *
     * @param mixed $message MQTT受信メッセージ
     * @param string $topic トピック名
     * @return array<string,mixed>|null
     */
    private function decodePayload(mixed $message, string $topic): ?array
    {
        $payload = json_decode((string) $message, true);
        if (!is_array($payload)) {
            Log::warning('Invalid MQTT payload.', ['topic' => $topic, 'message' => (string) $message]);
            return null;
        }

        return $payload;
    }

    /**
     * ハートビートトピックの購読
     *
     * @param array{ipAddress: string, cpuTemperature: float, cpuUtilization: float} $heartbeat 受信したハートビートデータ
     * @return void
     */
    private function subscribeHeartbeat(array $heartbeat)
    {
        try {
            $ipAddress = $heartbeat['ipAddress'];
            $cpuTemperature = $heartbeat['cpuTemperature'];
            $cpuUtilization = $heartbeat['cpuUtilization'];
            $this->raspberryPiService->updateCpuInfo($ipAddress, $cpuTemperature, $cpuUtilization);
        } catch (Throwable $e) {
            Log::error($e->getMessage(), $e->getTrace());
        }
    }

    /**
     * 生産数通知用トピックの購読
     *
     * @param array{ipAddress: string, count: int, pinNumber: int|string} $production 生産数データ
     * @return void
     */
    private function subscribeProduction(array $production)
    {
        try {
            $ipAddress = $production['ipAddress'];
            $count = $production['count'];
            $pinNumber = $production['pinNumber'];
            $dateTime = Utility::now();
            $this->productionService->store($ipAddress, $count, $pinNumber, $dateTime);
        } catch (ProductionException $e) {
            // なにもしない
        } catch (Throwable $e) {
            Log::error($e->getMessage(), $e->getTrace());
        }
    }

    /**
     * バーコード読取通知用トピックの購読
     *
     * @param array{ipAddress: string, macAddress: string, barcode: string} $barcodeData バーコードデータ
     * @return void
     */
    private function subscribeBarcode(array $barcodeData)
    {
        try {
            $ipAddress = $barcodeData['ipAddress'];
            $macAddress = $barcodeData['macAddress'];
            $barcode = $barcodeData['barcode'];
            if ($this->productionHistoryService->switchPartNumberFromMqtt($ipAddress, $macAddress, $barcode)) {
                $this->barcodeHistoryService->store($ipAddress, $macAddress, $barcode);
            }
        } catch (Throwable $e) {
            Log::error($e->getMessage(), $e->getTrace());
        }
    }

    /**
     * センサーアラート通知用トピックの購読
     *
     * @param array{pinNumber:int|string, signal: bool, ipAddress: string, value: int|float} $data 通知データ
     * @return void
     */
    private function subscribeAlarm(array $data)
    {
        Log::debug('Alarm', $data);
        try {
            $pinNumber = $data['pinNumber'];
            $signal = $data['signal'];
            $ipAddress = $data['ipAddress'];
            $value = $data['value'];
            $this->sensorService->insert($pinNumber, $ipAddress, $signal, $value);
        } catch (Throwable $e) {
            Log::error($e->getMessage(), $e->getTrace());
        }
    }

    /**
     * ON-OFFメッセージ通知用トピックの購読
     *
     * @param array{onOff: bool, pinNumber: int, ipAddress: string} $data 通知データ
     * @return void
     */
    private function subscribeOnOff(array $data)
    {
        try {
            $isOn = $data['onOff'];
            $pinNumber = $data['pinNumber'];
            $ipAddress = $data['ipAddress'];
            $this->onOffService->insert($isOn, $pinNumber, $ipAddress);
        } catch (Throwable $e) {
            Log::error($e->getMessage(), $e->getTrace());
        }
    }

    /**
     * ガントチャート用トピックの購読
     *
     * @param array{ipAddress: string, signal: bool, pinNumber: int|string} $data ガントチャートデータ
     * @return void
     */
    private function subscribeGanttChart(array $data)
    {
        try {
            $signal = $data['signal'];
            $pinNumber = $data['pinNumber'];
            $ipAddress = $data['ipAddress'];
            $this->ganttChartService->insert($pinNumber, $ipAddress, $signal);
        } catch (Throwable $e) {
            Log::error($e->getMessage(), $e->getTrace());
        }
    }
}
