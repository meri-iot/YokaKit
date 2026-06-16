<?php

declare(strict_types=1);

namespace App\Exports;

use App\Models\Process;
use App\Services\GanttChartService;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * 複数シートのガントチャートをエクスポートするクラス
 */
class MultiSheetGanttChartExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(
        private readonly GanttChartService $service,
        private readonly Process $process,
        private readonly Carbon $startDate,
        private readonly Carbon $endDate,
    ) {}

    /**
     * @return array<int,GanttChartExport>
     */
    public function sheets(): array
    {
        // 指定期間でイベントを絞り込んだ工程データを取得し、各チャートを1シートとして出力する。
        $process = $this->service->getEventWithRange($this->process, $this->startDate, $this->endDate);

        /** @var array<string, bool> $usedTitles */
        $usedTitles = [];
        return $process->ganttCharts
            ->map(function ($ganttChart) use (&$usedTitles) {
                $baseTitle = GanttChartExport::normalizeSheetTitle($ganttChart->chart_name);
                $title = $this->uniqueSheetTitle($baseTitle, $usedTitles);
                $usedTitles[$title] = true;
                return new GanttChartExport($ganttChart, $title);
            })
            ->toArray();
    }

    /**
     * 既に利用済みのシート名と重複しないタイトルを生成する
     *
     * @param string $baseTitle ベースタイトル
     * @param array<string, bool> $usedTitles 利用済みタイトル
     * @return string 一意なシートタイトル
     */
    private function uniqueSheetTitle(string $baseTitle, array $usedTitles): string
    {
        if (!isset($usedTitles[$baseTitle])) {
            return $baseTitle;
        }

        $i = 2;
        while (true) {
            $suffix = " ($i)";
            $title = mb_substr($baseTitle, 0, max(0, 31 - mb_strlen($suffix))) . $suffix;
            if (!isset($usedTitles[$title])) {
                return $title;
            }
            $i++;
        }
    }
}
