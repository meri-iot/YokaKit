@extends('components.header')

@section('title', __('yokakit.gantt_chart'))

@section('content')
    @include('adminlte::partials.common.preloader')
    <div class="row">
        <div class="col-lg-12">
            @foreach ($processes as $process)
                @if ($process->ganttCharts->isNotEmpty())
                    <x-adminlte-card title="{{ $process->process_name }}" maximizable="true" collapsible>
                        <x-slot name="toolsSlot">
                            <a class="btn btn-tool" href="{{ route('process.show', ['process' => $process]) }}">
                                <i class="fa-solid fa-lg fa-share-from-square"></i>
                            </a>
                            <a class="btn btn-tool" href="{{ route('gantt-chart.history', ['process' => $process]) }}">
                                <i class="fa-solid fa-lg fa-history"></i>
                            </a>
                        </x-slot>
                        @php
                            $base = $process->ganttCharts->firstWhere('chart_type', \App\Enums\GanttChartType::BASE());
                            $works = $process->ganttCharts->where('chart_type', \App\Enums\GanttChartType::WORK());
                        @endphp
                        @if ($base && $works->isNotEmpty())
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
                                                <strong class="font-digit" id="operating-rate-{{ $work->gantt_chart_id }}">&nbsp;--</strong>
                                            </span>
                                        </h5>
                                    </div>
                                @endforeach
                            </div>
                        @endif
                        <div class="chart">
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
                    </x-adminlte-card>
                @endif
            @endforeach
        </div>
    </div>
@endsection

@section('js')
    @include('components.process.gantt-chartjs')
@endsection
