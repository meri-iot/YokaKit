<?php

declare(strict_types=1);

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
     * このリポジトリが扱うモデルクラスを返す
     *
     * @return class-string<Sensor>
     */
    public function model(): string
    {
        return Sensor::class;
    }
}
