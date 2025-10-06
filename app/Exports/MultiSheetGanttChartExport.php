<?php

namespace App\Exports;

use App\Models\Process;
use App\Services\GanttChartService;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

class MultiSheetGanttChartExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private readonly GanttChartService $service,
        private readonly Process $process,
        private readonly Carbon $startDate,
        private readonly Carbon $endDate,
    ) {}

    public function sheets(): array
    {
        $process = $this->service->getEventWithRange($this->process, $this->startDate, $this->endDate);
        return $process->ganttCharts->map(fn($ganttChart) => new GanttChartExport($ganttChart))->toArray();
    }
}
