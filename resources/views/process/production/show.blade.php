@extends('components.header', ['breadcrumbs' => $process])

@section('title', __('yokakit.display_chart'))

@section('content')
    @include('adminlte::partials.common.preloader')
    <div class="row">
        @php
            $startTime = $history->start->format('Y-m-d H:i');
            $endTime = $history->isComplete() ? $history->stop->format('Y-m-d H:i') : '';
            $plannedOutages = $history->productionPlannedOutages;
        @endphp
        {{-- 生産グラフ --}}
        <div class="col-lg-12">
            <x-adminlte-card title="{{ $history->process_name }} 【{{ $history->part_number_name }}】 {{ $startTime }} ~ {{ $endTime }}"
                maximizable="true">
                {{-- 生産数 --}}
                <div class="row">
                    @foreach ($lines as $line)
                        <div class="col-auto mr-2">
                            <h5>
                                <span>
                                    {{-- chart_colorはサニタイズ済み想定だが、念のためe()でエスケープ --}}
                                    <i class="fa-solid fa-fw fa-square" style="color: {{ e($line->chart_color) }}"></i>
                                    <span>{{ $line->line_name }}：</span>
                                    <strong class="font-digit" id="production-line-{{ $line->production_line_id }}">&nbsp;</strong>
                                </span>
                            </h5>
                        </div>
                    @endforeach
                    <div class="col-auto">
                        <h5>
                            <span>
                                <i class="fa-solid fa-fw fa-square" style="color: {{ $history->plan_color }}"></i>
                                {{ __('yokakit.plan_count') }}：
                                <strong class="font-digit" id="production-line-plan">0</strong>
                            </span>
                        </h5>
                    </div>
                    @isset($history->goal)
                        <div class="col-auto">
                            <h5>
                                <span>
                                    <i class="fa-regular fa-fw fa-square text-warning"></i>
                                    {{ __('yokakit.goal') }}：
                                    <strong class="font-digit">{{ $history->goal }}</strong>
                                </span>
                            </h5>
                        </div>
                    @endisset
                </div>
                <hr>
                {{-- 指標 --}}
                <div class="row">
                    <div class="col-auto mr-3">
                        <h5>
                            <i class="fa-solid fa-fw fa-square" id="indicator-icon"></i>
                            <span>
                                {{ __('yokakit.time_operating_rate') }}：
                                <strong class="font-digit" id="time-operating-rate">&nbsp;</strong>
                                %
                            </span>
                        </h5>
                    </div>
                    <div class="col-auto mr-3">
                        <h5>
                            <span>
                                {{ __('yokakit.performance_operating_rate') }}：
                                <strong class="font-digit" id="performance-operating-rate">&nbsp;</strong>
                                %
                            </span>
                        </h5>
                    </div>
                    <div class="col-auto mr-3">
                        <h5>
                            <span>
                                {{ __('yokakit.achievement_rate') }}：
                                <strong class="font-digit" id="achievement-rate">&nbsp;</strong>
                                %
                            </span>
                        </h5>
                    </div>
                    <div class="col-auto mr-3">
                        <h5>
                            <span>
                                {{ __('yokakit.good_rate') }}：
                                <strong class="font-digit" id="good-rate">&nbsp;</strong>
                                %
                            </span>
                        </h5>
                    </div>
                    <div class="col-auto mr-3">
                        <h5>
                            <span>
                                {{ __('yokakit.cycle_time') }}：
                                <strong class="font-digit" id="cycle-time">&nbsp;</strong>
                                sec
                            </span>
                        </h5>
                    </div>
                    <div class="col-auto mr-3">
                        <h5>
                            <span>
                                {{ __('yokakit.overall_equipment_effectiveness') }}：
                                <strong class="font-digit" id="overall-equipment-effectiveness">&nbsp;</strong>
                            </span>
                            %
                        </h5>
                    </div>
                </div>
                {{-- グラフ表示 --}}
                <div class="row">
                    <div class="chart col-lg-12 mx-auto mt-4">
                        <div class="chartjs-size-monitor">
                            <div class="chartjs-size-monitor-expand">
                                <div class=""></div>
                            </div>
                            <div class="chartjs-size-monitor-shrink">
                                <div class=""></div>
                            </div>
                        </div>
                        <canvas class="chartjs-render-monitor" id="production"></canvas>
                    </div>
                </div>
            </x-adminlte-card>
        </div>
    </div>
@endsection

@section('js')
    @include('components.process.chartjs')
@endsection
