<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * 作業者モデルクラス
 *
 * @property int $worker_id 主キー
 * @property string $identification_number 作業者識別番号
 * @property string $worker_name 作業者名
 * @property string|null $mac_address MACアドレス
 * @property Collection<int,Process> $processes 工程
 */
class Worker extends Model
{
    use HasFactory;

    /**
     * モデルの主キー名
     *
     * @var string
     */
    protected $primaryKey = 'worker_id';

    /**
     * 代入可能な属性
     *
     * @var array<int,string>
     */
    protected $fillable = [
        'identification_number',    // 作業者識別番号
        'worker_name',              // 作業者名
        'mac_address',              // バーコードリーダーMACアドレス
    ];

    /**
     * シリアライズ時に隠す属性
     *
     * @var array<int,string>
     */
    protected $hidden = [
        'created_at',   // 作成時刻を隠す
        'updated_at',   // 更新時刻を隠す
    ];

    /**
     * 多対多の関係を定義します。
     * 作業者と関連する「工程」データを取得します。
     *
     * @return BelongsToMany
     */
    public function processes(): BelongsToMany
    {
        return $this->belongsToMany(Process::class, 'lines', $this->primaryKey, 'process_id')
            ->using(Line::class)
            ->withPivot('line_id');
    }
}
