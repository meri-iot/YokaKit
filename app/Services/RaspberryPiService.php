<?php

declare(strict_types=1);

namespace App\Services;

use App\Http\Requests\StoreRaspberryPiRequest;
use App\Http\Requests\UpdateRaspberryPiRequest;
use App\Models\RaspberryPi;
use App\Repositories\RaspberryPiRepository;
use Illuminate\Database\Eloquent\Collection;

/**
 * ラズパイサービス
 */
class RaspberryPiService
{
    /**
     * コンストラクタ
     *
     * サービスロケータを介さず、依存を明示的に受け取ることで
     * テスト容易性と依存関係の可読性を高める。
     */
    public function __construct(
        private readonly RaspberryPiRepository $raspberryPi,
    ) {}

    /**
     * すべてのラズベリーパイを取得する
     *
     * @return Collection<int,RaspberryPi>
     */
    public function all()
    {
        return $this->raspberryPi->all();
    }

    /**
     * ラズベリーパイを追加する
     *
     * @param StoreRaspberryPiRequest $request ラズベリーパイ追加リクエスト
     * @return bool 成否
     */
    public function store(StoreRaspberryPiRequest $request): bool
    {
        return $this->raspberryPi->store($request);
    }

    /**
     * ラズベリーパイを更新する
     *
     * @param UpdateRaspberryPiRequest $request ラズベリーパイ更新リクエスト
     * @param RaspberryPi $raspberryPi 更新対象のラズベリーパイ
     * @return bool 成否
     */
    public function update(UpdateRaspberryPiRequest $request, RaspberryPi $raspberryPi): bool
    {
        return $this->raspberryPi->update($request, $raspberryPi);
    }

    /**
     * ラズベリーパイを削除する
     *
     * @param RaspberryPi $raspberryPi 削除対象のラズベリーパイ
     * @return bool 成否
     */
    public function destroy(RaspberryPi $raspberryPi): bool
    {
        return $this->raspberryPi->destroy($raspberryPi);
    }

    /**
     * ラズベリーパイのCPU情報を更新する
     *
     * @param string $ipAddress ラズベリーパイのIPアドレス
     * @param float $cpuTemperature CPU温度
     * @param float $cpuUtilization CPU使用率
     * @return bool 成否
     */
    public function updateCpuInfo(string $ipAddress, float $cpuTemperature, float $cpuUtilization): bool
    {
        return $this->raspberryPi->updateCpuInfo($ipAddress, $cpuTemperature, $cpuUtilization);
    }
}
