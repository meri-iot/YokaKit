@extends('components.header', ['breadcrumbs' => $process])

@section('title', $process->process_name . '：' . __('yokakit.gantt_chart_history'))

@section('content')
    @include('adminlte::partials.common.preloader')
    <x-adminlte-card body-class="">
        @php
            $processes = [$process];
        @endphp
        <div class="row p-3">
            <div class="col-4">
                <x-daterange name="date-range"></x-daterange>
            </div>
            <div class="d-flex flex-column justify-content-end col-auto">
                <button class="btn btn-default mb-3" id="search-button">
                    <i class="fa-solid fa-lg fa-search"></i>
                    {{ __('yokakit.search') }}
                </button>
            </div>
            <div class="d-flex flex-column justify-content-end col-auto">
                <button class="btn btn-default mb-3" id="download-button">
                    <i class="fa-solid fa-lg fa-download"></i>
                    {{ __('yokakit.download') }}
                </button>
            </div>
        </div>
        @php
            $base = $process->ganttCharts->firstWhere('chart_type', \App\Enums\GanttChartType::BASE());
            $works = $process->ganttCharts->where('chart_type', \App\Enums\GanttChartType::WORK());
        @endphp
        @if ($base && $works)
            <div class="row">
                <h5 class="ml-2 mr-2">
                    {{ __('yokakit.operating_rate') }}
                </h5>
                @foreach ($works as $work)
                    <div class="col-auto mr-2">
                        <h5>
                            <span>
                                {{-- chart_colorはサニタイズ済み想定だが、念のためe()でエスケープ --}}
                                <i class="fa-solid fa-fw fa-square" style="color: {{ e($work->chart_color) }}"></i>
                                <span>{{ $work->chart_name }}：</span>
                                @if ($work->base_span == 0)
                                    <strong class="font-digit">--</strong>
                                @else
                                    <strong class="font-digit">{{ (int) (($work->overlap_span * 100) / $work->base_span) }}%</strong>
                                @endif
                            </span>
                        </h5>
                    </div>
                @endforeach
            </div>
        @endif
        @if ($process->ganttCharts->count() != 0)
            <div class="chart mx-auto">
                <div class="chartjs-size-monitor">
                    <div class="chartjs-size-monitor-expand">
                        <div class=""></div>
                    </div>
                    <div class="chartjs-size-monitor-shrink">
                        <div class=""></div>
                    </div>
                </div>
                <canvas class="chartjs-render-monitor" id="gantt-chart-{{ $process->process_id }}"></canvas>
            </div>
        @endif
    </x-adminlte-card>
@endsection

@section('js')
    @include('components.process.gantt-chartjs')
    <script>
        const searchUrl = @json(route('gantt-chart.history', ['process' => $process]));
        const downloadUrl = @json(route('gantt-chart.download', ['process' => $process]));
        const url = new URL(window.location.href);
        const queryStartDate = url.searchParams.get('startDate');
        const queryEndDate = url.searchParams.get('endDate');
        let startDate = moment(queryStartDate, 'YYYY-MM-DD', true).isValid() ? queryStartDate : moment().startOf('day').format('YYYY-MM-DD');
        let endDate = moment(queryEndDate, 'YYYY-MM-DD', true).isValid() ? queryEndDate : moment().endOf('day').format('YYYY-MM-DD');

        $('#search-button').on('click', function() {
            const params = new URLSearchParams();
            params.set('startDate', startDate);
            params.set('endDate', endDate);
            const urlWithQuery = `${searchUrl}?${params.toString()}`;
            window.location.href = urlWithQuery;
        });
        $('#download-button').on('click', function() {
            const params = new URLSearchParams();
            params.set('startDate', startDate);
            params.set('endDate', endDate);
            const urlWithQuery = `${downloadUrl}?${params.toString()}`;
            const a = document.createElement('a');
            a.href = urlWithQuery;
            a.download = '';
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
        });
        $(function() {
            const dateRange = $('#date-range');
            const picker = dateRange.data('daterangepicker');
            picker.setStartDate(moment(startDate).startOf('day'));
            picker.setEndDate(moment(endDate).endOf('day'));
            dateRange.on('change', function() {
                startDate = picker.startDate.format('YYYY-MM-DD');
                endDate = picker.endDate.format('YYYY-MM-DD');
            });
        });
    </script>
@endsection
