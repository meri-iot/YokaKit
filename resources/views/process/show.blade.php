@extends('components.header', ['breadcrumbs' => $process])

@section('meta_tags')
    <meta name="csrf-token" content="{{ csrf_token() }}">
@endsection

@section('title', $process->process_name)

@section('content')
    @if (!$process->isStopped())
        @include('adminlte::partials.common.preloader')
        @php
            $history = $process->productionHistory;
            $startTime = $lines->filter(fn($x) => $x->defective === false)->first()->productions->first()->at->format('Y-m-d H:i');
        @endphp
        <div class="row">
            {{-- 生産グラフ --}}
            <div class="col-lg-12">
                <x-adminlte-card title="{{ $history?->process_name }} 【{{ $history?->part_number_name }}】 {{ $startTime }} ~" maximizable="true"
                    collapsible>
                    <x-slot name="toolsSlot">
                        {{-- 品番切り替えボタン --}}
                        <a class="btn btn-tool" href="{{ route('production.create', ['process' => $process]) }}" role="button">
                            <i class="fa-solid fa-lg fa-rotate"></i>
                        </a>
                        @if ($process->isChangeover())
                            {{-- 生産開始ボタン --}}
                            <x-adminlte-button data-toggle="modal" data-target="#stop-changeover" theme="tool" icon="fa-solid fa-lg fa-play" />
                            {{-- 生産開始ダイアログ --}}
                            <x-adminlte-modal id="stop-changeover" title="{{ __('yokakit.confirm') }}" theme="info" icon="fa-solid fa-fw fa-info"
                                v-centered>
                                <strong>{{ __('yokakit.confirm_production') }}</strong>
                                <x-adminlte-card class="mt-4">
                                    <strong>{{ __('yokakit.target_name', ['target' => __('yokakit.part_number')]) }}</strong>
                                    <p class="ml-2 mt-1">{{ $process->productionHistory->part_number_name }}</p>
                                </x-adminlte-card>
                                <x-slot name="footerSlot">
                                    <form action="{{ route('production.stop_changeover', ['process' => $process]) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <x-adminlte-button type="submit" theme="info" label="{{ __('yokakit.start_production') }}"
                                            icon="fa-solid fa-fw fa-play" />
                                    </form>
                                </x-slot>
                            </x-adminlte-modal>
                        @else
                            {{-- 段取り替えボタン --}}
                            <x-adminlte-button data-toggle="modal" data-target="#start-changeover" theme="tool" icon="fa-solid fa-lg fa-pause" />
                            {{-- 段取り替えダイアログ --}}
                            <x-adminlte-modal id="start-changeover" title="{{ __('yokakit.confirm') }}" theme="warning"
                                icon="fa-solid fa-fw fa-triangle-exclamation" v-centered>
                                <strong>{{ __('yokakit.confirm_changeover') }}</strong>
                                <x-adminlte-card class="mt-4">
                                    <strong>{{ __('yokakit.target_name', ['target' => __('yokakit.part_number')]) }}</strong>
                                    <p class="ml-2 mt-1">{{ $process->productionHistory->part_number_name }}</p>
                                </x-adminlte-card>
                                <x-slot name="footerSlot">
                                    <form action="{{ route('production.start_changeover', ['process' => $process]) }}" method="POST">
                                        @csrf
                                        @method('PUT')
                                        <x-adminlte-button type="submit" theme="warning" label="{{ __('yokakit.changeover') }}"
                                            icon="fa-solid fa-fw fa-pause" />
                                    </form>
                                </x-slot>
                            </x-adminlte-modal>
                        @endif
                        {{-- 停止ボタン --}}
                        <x-adminlte-button data-toggle="modal" data-target="#stop" theme="tool" icon="fa-solid fa-lg fa-stop" />
                        {{-- 停止ダイアログ --}}
                        <x-adminlte-modal id="stop" title="{{ __('yokakit.confirm') }}" theme="danger" icon="fa-solid fa-fw fa-ban" v-centered>
                            <strong>{{ __('yokakit.confirm_stop') }}</strong>
                            <x-adminlte-card class="mt-4">
                                <strong>{{ __('yokakit.target_name', ['target' => __('yokakit.part_number')]) }}</strong>
                                <p class="ml-2 mt-1">{{ $process->productionHistory->part_number_name }}</p>
                            </x-adminlte-card>
                            <x-slot name="footerSlot">
                                <form action="{{ route('production.stop', ['process' => $process]) }}" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <x-adminlte-button type="submit" theme="danger" label="{{ __('yokakit.stop') }}" icon="fa-solid fa-fw fa-stop" />
                                </form>
                            </x-slot>
                        </x-adminlte-modal>
                    </x-slot>
                    {{-- 生産数 --}}
                    <div class="row">
                        @foreach ($lines ?? [] as $line)
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
                                    <i class="fa-solid fa-fw fa-square" style="color: {{ $history?->plan_color }}"></i>
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
                                        <strong class="font-digit">{{ $history?->goal }}</strong>
                                    </span>
                                </h5>
                            </div>
                        @endisset
                        {{-- ステータス --}}
                        <div class="ml-auto">
                            <h5 id="status-area">
                                <span class="badge" id="production-status" style="font-size: 100%;"></span>
                            </h5>
                        </div>
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
    @endif
    <div class="row">
        <div class="col-lg-12">
            <div class="card">
                @php
                    $tab = request()->query('tab');
                @endphp
                <div class="card-header p-0 pt-1">
                    {{-- タブナビゲーション --}}
                    <ul class="nav nav-tabs" id="process-tab" role="tablist">
                        <li class="nav-item">
                            <a class="nav-link @if (!$tab) active @endif" id="process-home-tab" data-toggle="pill"
                                href="#process-home" role="tab" aria-controls="process-home" aria-selected="false">
                                {{ __('yokakit.process') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if ($tab == 'part-number') active @endif" id="process-part-number-tab" data-toggle="pill"
                                href="#process-part-number" role="tab" aria-controls="process-part-number" aria-selected="false">
                                {{ __('yokakit.part_number') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if ($tab === 'line') active @endif" id="process-line-tab" data-toggle="pill"
                                href="#process-line" role="tab" aria-controls="process-line" aria-selected="false">
                                {{ __('yokakit.line') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if ($tab === 'planned-outage') active @endif" id="process-planned-outage-tab" data-toggle="pill"
                                href="#process-planned-outage" role="tab" aria-controls="process-planned-outage" aria-selected="false">
                                {{ __('yokakit.planned_outage') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if ($tab === 'alarm') active @endif" id="process-alarm-tab" data-toggle="pill"
                                href="#process-alarm" role="tab" aria-controls="process-alarm" aria-selected="false">
                                {{ __('yokakit.alarm') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if ($tab === 'on-off') active @endif" id="process-on-off-tab" data-toggle="pill"
                                href="#process-on-off" role="tab" aria-controls="process-on-off" aria-selected="false">
                                {{ __('yokakit.notification') }}
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link @if ($tab === 'gantt-chart') active @endif" id="process-gantt-chart-tab" data-toggle="pill"
                                href="#process-gantt-chart" role="tab" aria-controls="process-gantt-chart" aria-selected="false">
                                {{ __('yokakit.gantt_chart') }}
                            </a>
                        </li>
                    </ul>
                </div>
                <div class="card-body p-0">
                    {{-- タブコンテンツ --}}
                    <div class="tab-content" id="process-tabContent">
                        <div class="tab-pane fade @if (!$tab) active show @endif" id="process-home" role="tabpanel"
                            aria-labelledby="process-home-tab">
                            {{-- 工程 --}}
                            <x-process.detail :process="$process" />
                        </div>
                        <div class="tab-pane fade @if ($tab === 'part-number') active show @endif" id="process-part-number" role="tabpanel"
                            aria-labelledby="process-part-number-tab">
                            {{-- 品番 --}}
                            <x-process.part-number :process="$process" />
                        </div>
                        <div class="tab-pane fade @if ($tab === 'line') active show @endif" id="process-line" role="tabpanel"
                            aria-labelledby="process-line-tab">
                            {{-- ライン --}}
                            <x-process.line :process="$process" />
                        </div>
                        <div class="tab-pane fade @if ($tab === 'planned-outage') active show @endif" id="process-planned-outage" role="tabpanel"
                            aria-labelledby="process-planned-outage-tab">
                            {{-- 計画停止時間 --}}
                            <x-process.planned-outage :process="$process" />
                        </div>
                        <div class="tab-pane fade @if ($tab === 'alarm') active show @endif" id="process-alarm" role="tabpanel"
                            aria-labelledby="process-alarm-tab">
                            {{-- アラーム --}}
                            <x-process.alarm :process="$process" />
                        </div>
                        <div class="tab-pane fade @if ($tab === 'on-off') active show @endif" id="process-on-off" role="tabpanel"
                            aria-labelledby="process-on-off-tab">
                            {{-- 通知 --}}
                            <x-process.on-off :process="$process" />
                        </div>
                        <div class="tab-pane fade @if ($tab === 'gantt-chart') active show @endif" id="process-gantt-chart" role="tabpanel"
                            aria-labelledby="process-gantt-chart-tab">
                            {{-- ガントチャート --}}
                            <x-process.gantt-chart :process="$process" />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
@endsection

@section('js')
    <script>
        $(() => {
            const processId = @json($process->process_id);
            const productionHistoryId = @json($process->productionHistory?->production_history_id ?? 0);
            let currentStatus = @json($process->productionHistory?->statusName ?? 'COMPLETE');

            Echo.join('summary')
                .listen('ProductionSummaryNotification', (data) => {
                    console.log('ProductionSummaryNotification', data);
                    if (data.processId !== processId) {
                        return;
                    }
                    if (productionHistoryId === 0 && data.statusName === currentStatus) {
                        return;
                    }
                    if (productionHistoryId !== data.productionHistoryId) {
                        location.reload();
                    }
                    if (data.statusName === currentStatus) {
                        return;
                    }
                    if (data.statusName === 'CHANGEOVER') {
                        location.reload();
                    }
                    if (data.statusName === 'COMPLETE') {
                        location.reload();
                    }
                    if (data.statusName === 'RUNNING' && currentStatus === 'CHANGEOVER') {
                        location.reload();
                    }
                    if (data.statusName === 'RUNNING' && currentStatus === 'COMPLETE') {
                        location.reload();
                    }
                    currentStatus = data.statusName;
                });
        });
    </script>
    @include('components.toast')
    @if (!$process->isStopped())
        @include('components.process.chartjs')
    @endif
@endsection
