<?php

namespace App\Repositories;

use App\Models\Sensor;

/**
 * センサーリポジトリ
 *
 * @extends AbstractRepository<Sensor>
 */
class SensorRepository extends AbstractRepository
{
    /**
     * モデルクラス
     *
     * @return class-string
     */
    public function model(): string
    {
        return Sensor::class;
    }
}
