<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * ラズベリーパイモデルクラス
 *
 * @property integer $raspberry_pi_id 主キー
 * @property string $raspberry_pi_name ラズパイ名
 * @property string $ip_address IPアドレス
 * @property float|null $cpu_temperature CPU温度
 * @property float|null $cpu_utilization CPU使用率
 * @property Line|null $pivot ラインモデル
 * @property Collection<int,Process> $processes 工程モデル
 */
class RaspberryPi extends Model
{
    use HasFactory;

    /**
     * モデルの主キー名
     *
     * @var string
     */
    protected $primaryKey = 'raspberry_pi_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'raspberry_pi_name',    // ラズパイ名
        'ip_address',           // IPアドレス
        'cpu_temperature',      // CPU温度
        'cpu_utilization',      // CPU使用率
    ];

    /**
     * シリアライズ時に隠す属性
     *
     * @var array<int,string>
     */
    protected $hidden = [
        'created_at',   // 作成時刻を隠す
    ];

    /**
     * ラズベリーパイと多対多で関連する工程を取得する
     *
     * @return BelongsToMany
     */
    public function processes(): BelongsToMany
    {
        return $this->belongsToMany(Process::class, 'lines', 'raspberry_pi_id', 'process_id')
            ->using(Line::class)
            ->withPivot(['line_id', 'line_name', 'chart_color', 'pin_number', 'defective']);
    }
}
