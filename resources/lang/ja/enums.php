<?php

use App\Enums\GanttChartType;
use App\Enums\ProductionStatus;
use App\Enums\RoleType;

return [
    ProductionStatus::class => [
        ProductionStatus::RUNNING => '稼働中',
        ProductionStatus::CHANGEOVER => '段取り替え',
        ProductionStatus::BREAKDOWN => 'チョコ停',
        ProductionStatus::COMPLETE => '停止',
    ],
    RoleType::class => [
        RoleType::SYSTEM => 'システム管理者',
        RoleType::ADMIN => '管理者',
        RoleType::USER => 'ユーザー',
    ],
    GanttChartType::class => [
        GanttChartType::NONE => '-',
        GanttChartType::BASE => '操業',
        GanttChartType::WORK => '稼働',
    ],
];
