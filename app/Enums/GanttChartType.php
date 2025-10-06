<?php

declare(strict_types=1);

namespace App\Enums;

use BenSampo\Enum\Contracts\LocalizedEnum;
use BenSampo\Enum\Enum;

/**
 * ガントチャート種別
 *
 * @method static ProductionStatus NONE() 未定義
 * @method static ProductionStatus BASE() 基準信号
 * @method static ProductionStatus WORK() 稼働信号
 */
final class GanttChartType extends Enum implements LocalizedEnum
{
    public const NONE = 1;
    public const BASE = 2;
    public const WORK = 3;
}
