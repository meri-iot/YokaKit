<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\RaspberryPi;
use Illuminate\Database\Eloquent\Collection;

/**
 * ラズベリーパイリポジトリ
 *
 * @extends AbstractRepository<RaspberryPi>
 */
class RaspberryPiRepository extends AbstractRepository
{
    /**
     * このリポジトリが扱うモデルクラスを返す
     *
     * @return class-string<RaspberryPi>
     */
    public function model(): string
    {
        return RaspberryPi::class;
    }

    /**
     * CPU情報を更新する
     *
     * 指定IPに一致するラズパイが存在しない場合は false を返す。
     *
     * @param string $ipAddress IPアドレス
     * @param float $cpuTemperature CPU温度
     * @param float $cpuUtilization CPU使用率
     * @return bool 成否
     */
    public function updateCpuInfo(string $ipAddress, float $cpuTemperature, float $cpuUtilization): bool
    {
        return $this->updateModel(
            $this->model->where('ip_address', $ipAddress),
            [
                'cpu_temperature' => $cpuTemperature,
                'cpu_utilization' => $cpuUtilization,
            ]
        );
    }

    /**
     * ラズベリーパイ選択用のオプションを取得する
     *
     * @return array<int,string>
     */
    public function options(): array
    {
        /** @var Collection<int,RaspberryPi> */
        $raspberryPis = $this->all(order: 'ip_address');
        return $raspberryPis->reduce(function (array $carry, RaspberryPi $raspi) {
            $carry[$raspi->raspberry_pi_id] = "{$raspi->raspberry_pi_name} : {$raspi->ip_address}";
            return $carry;
        }, []);
    }
}
