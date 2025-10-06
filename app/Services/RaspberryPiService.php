<?php

namespace App\Services;

use App\Http\Requests\StoreRaspberryPiRequest;
use App\Http\Requests\UpdateRaspberryPiRequest;
use App\Models\RaspberryPi;
use App\Repositories\RaspberryPiRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\App;

/**
 * ラズパイサービス
 */
class RaspberryPiService
{
    private readonly RaspberryPiRepository $raspberryPi;

    /**
     * コンストラクタ
     */
    public function __construct()
    {
        $this->raspberryPi = App::make(RaspberryPiRepository::class);
    }

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
     * @return boolean 成否
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
     * @return boolean 成否
     */
    public function update(UpdateRaspberryPiRequest $request, RaspberryPi $raspberryPi): bool
    {
        return $this->raspberryPi->update($request, $raspberryPi);
    }

    /**
     * ラズベリーパイを削除する
     *
     * @param RaspberryPi $raspberryPi 削除対象のラズベリーパイ
     * @return boolean 成否
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
     * @return boolean 成否
     */
    public function updateCpuInfo(string $ipAddress, float $cpuTemperature, float $cpuUtilization): bool
    {
        return $this->raspberryPi->updateCpuInfo($ipAddress, $cpuTemperature, $cpuUtilization);
    }
}
