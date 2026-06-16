@extends('components.header')

@section('title', __('yokakit.home'))

@section('content')
    @include('adminlte::partials.common.preloader')
    <div class="row">
        <div class="col-md-12">
            <x-adminlte-card title="{{ __('yokakit.andon') }}" icon="fa-solid fa-fw fa-tower-observation" maximizable="true">
                <div id="andon-slider">
                    @foreach ($processes->chunk($config->chunkLength()) as $chunk)
                        <div>
                            <div class="row pl-1 pr-1">
                                @foreach ($chunk as $process)
                                    @php
                                        $id = "process-{$process->process_id}";
                                        $url = route('process.show', ['process' => $process]);
                                        $urlText = __('yokakit.detail');
                                        $summary = $process->productionHistory?->summary();
                                        $inPlannedOutage = $process->productionHistory?->inPlannedOutage() ?? false;
                                        if ($process->isStopped()) {
                                            $title = __('yokakit.stop');
                                            $icon = '';
                                            $theme = '';
                                        } elseif ($process->sensorEvents->count() === 0) {
                                            switch ($process->status()) {
                                                case \App\Enums\ProductionStatus::RUNNING():
                                                    $title = $process->status()->description;
                                                    $icon = 'fas fa-info';
                                                    $theme = 'white';
                                                    break;
                                                case \App\Enums\ProductionStatus::BREAKDOWN():
                                                    if ($inPlannedOutage) {
                                                        $title = __('yokakit.planned_outage');
                                                        $icon = 'fas fa-info';
                                                        $theme = 'info';
                                                    } else {
                                                        $title = $process->status()->description;
                                                        $icon = 'fas fa-ban';
                                                        $theme = 'danger';
                                                    }
                                                    break;
                                                case \App\Enums\ProductionStatus::CHANGEOVER():
                                                    $title = $process->status()->description;
                                                    $icon = 'fas fa-rotate';
                                                    $theme = 'warning';
                                                    break;
                                                default:
                                                    break;
                                            }
                                        } else {
                                            $title = $process->sensorEvents[0]->alarm_text;
                                            $icon = 'fas fa-ban';
                                            $theme = 'orange';
                                        }
                                    @endphp
                                    <div class="col-md-{{ floor(12 / $config->column_count) }} @if (!$process->andonLayout->is_display) invisible @endif">
                                        @if ($process->andonLayout->is_display)
                                            <x-adminlte-small-box id="{{ $id }}" title="{{ $title }}" text="{{ $process->process_name }}"
                                                url="{{ $url }}" url-text="{{ $urlText }}" icon="{{ $icon }}"
                                                theme="{{ $theme }}">
                                                @if ($config->is_show_part_number)
                                                    <x-adminlte-profile-col-item id="part-number-{{ $process->process_id }}"
                                                        title="{{ __('yokakit.part_number') }}"
                                                        text="{{ $process->productionHistory?->part_number_name ?? '--' }}"
                                                        size="{{ 12 / $config->item_column_count }}" />
                                                @endif
                                                @if ($config->is_show_start)
                                                    <x-adminlte-profile-col-item id="start-{{ $process->process_id }}"
                                                        title="{{ __('yokakit.start_time') }}" text="{{ $process->productionHistory?->start ?? '--' }}"
                                                        size="{{ 12 / $config->item_column_count }}" />
                                                @endif
                                                @php $summary = $summary ?? []; @endphp
                                                @if ($config->is_show_good_count)
                                                    <x-adminlte-profile-col-item id="good-count-{{ $process->process_id }}"
                                                        title="{{ __('yokakit.good_count') }}" text="{{ $summary['goodCount'] ?? '--' }}"
                                                        size="{{ floor(12 / $config->item_column_count) }}" />
                                                @endif
                                                @if ($config->is_show_defective_count)
                                                    <x-adminlte-profile-col-item id="defective-count-{{ $process->process_id }}"
                                                        title="{{ __('yokakit.defective_count') }}" text="{{ $summary['defectiveCount'] ?? '--' }}"
                                                        size="{{ floor(12 / $config->item_column_count) }}" />
                                                @endif
                                                @if ($config->is_show_good_rate)
                                                    <x-adminlte-profile-col-item id="good-rate-{{ $process->process_id }}"
                                                        title="{{ __('yokakit.good_rate') }}" text="{{ $summary['goodRate'] ?? '--' }}"
                                                        size="{{ floor(12 / $config->item_column_count) }}" />
                                                @endif
                                                @if ($config->is_show_defective_rate)
                                                    <x-adminlte-profile-col-item id="defective-rate-{{ $process->process_id }}"
                                                        title="{{ __('yokakit.defective_rate') }}" text="{{ $summary['defectiveRate'] ?? '--' }}"
                                                        size="{{ floor(12 / $config->item_column_count) }}" />
                                                @endif
                                                @if ($config->is_show_plan_count)
                                                    <x-adminlte-profile-col-item id="plan-count-{{ $process->process_id }}"
                                                        title="{{ __('yokakit.plan_count') }}" text="{{ $summary['planCount'] ?? '--' }}"
                                                        size="{{ floor(12 / $config->item_column_count) }}" />
                                                @endif
                                                @if ($config->is_show_achievement_rate)
                                                    <x-adminlte-profile-col-item id="achievement-rate-{{ $process->process_id }}"
                                                        title="{{ __('yokakit.achievement_rate') }}" text="{{ $summary['achievementRate'] ?? '--' }}"
                                                        size="{{ floor(12 / $config->item_column_count) }}" />
                                                @endif
                                                @if ($config->is_show_cycle_time)
                                                    <x-adminlte-profile-col-item id="cycle-time-{{ $process->process_id }}"
                                                        title="{{ __('yokakit.cycle_time') }}" text="{{ $summary['cycleTime'] ?? '--' }}"
                                                        size="{{ floor(12 / $config->item_column_count) }}" />
                                                @endif
                                                @if ($config->is_show_time_operating_rate)
                                                    <x-adminlte-profile-col-item id="time-operating-rate-{{ $process->process_id }}"
                                                        title="{{ __('yokakit.time_operating_rate') }}"
                                                        text="{{ $summary['timeOperatingRate'] ?? '--' }}"
                                                        size="{{ floor(12 / $config->item_column_count) }}" />
                                                @endif
                                                @if ($config->is_show_performance_operating_rate)
                                                    <x-adminlte-profile-col-item id="performance-operating-rate-{{ $process->process_id }}"
                                                        title="{{ __('yokakit.performance_operating_rate') }}"
                                                        text="{{ $summary['performanceOperatingRate'] ?? '--' }}"
                                                        size="{{ floor(12 / $config->item_column_count) }}" />
                                                @endif
                                                @if ($config->is_show_overall_equipment_effectiveness)
                                                    <x-adminlte-profile-col-item id="oee-{{ $process->process_id }}"
                                                        title="{{ __('yokakit.overall_equipment_effectiveness') }}"
                                                        text="{{ $summary['overallEquipmentEffectiveness'] ?? '--' }}"
                                                        size="{{ floor(12 / $config->item_column_count) }}" />
                                                @endif
                                                @if ($config->is_show_goal)
                                                    <x-adminlte-profile-col-item id="goal-{{ $process->process_id }}" title="{{ __('yokakit.goal') }}"
                                                        text="{{ $process->productionHistory?->goal ?? '--' }}"
                                                        size="{{ 12 / $config->item_column_count }}" />
                                                @endif
                                        @endif
                                        </x-adminlte-small-box>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
                <x-slot name="toolsSlot">
                    <a class="btn btn-tool pb-0 pt-0" href="{{ route('andon.config') }}" role="button">
                        <i class="fa-solid fa-lg fa-gear"></i>
                    </a>
                </x-slot>
            </x-adminlte-card>
        </div>
    </div>
@endsection

@section('js')
    @include('components.toast')
@endsection

@push('js')
    <script>
        $(() => {

            const config = @json($config);
            const slider = $('#andon-slider');
            const slideSpeed = config.slide_speed;

            slider.slick({
                autoplay: config.auto_play,
                autoplaySpeed: config.auto_play_speed,
                cssEase: config.easing,
                arrows: false,
                dots: true,
                infinite: true,
                fade: config.fade,
                speed: slideSpeed,
            });

            let processes = @json($processes);
            if (!Array.isArray(processes)) {
                processes = Object.values(processes);
            }
            processes = processes.filter(p => p.andon_layout && p.andon_layout.is_display);
            console.log('processes', processes);

            const payloads = processes
                .reduce((acc, p) => {
                    acc[p.process_id] = p.production_summary ? new Payload(p.production_summary) : null;
                    return acc;
                }, {});
            console.log('payloads', payloads);

            const _processes = Object.values(processes)
                .filter(x => x.andon_layout.is_display)
                .map(x => {
                    const obj = {};
                    obj[x.process_id] = new _AdminLTE_SmallBox(`process-${x.process_id}`);
                    return obj;
                });
            const smallBoxes = _processes.length === 0 ? {} : Object.assign(..._processes);
            console.log('smallBoxes', smallBoxes);

            const alarms = processes.reduce((carry, process) => {
                const alarm = {
                    status: process.production_history?.status_name ?? 'COMPLETE',
                    events: process.sensor_events,
                    sensorId: process.sensor_events[0]?.sensor_id,
                    processId: process.process_id,
                };
                const smallBox = smallBoxes[process.process_id];
                showAlarm(smallBox, alarm, null, process);
                carry[process.process_id] = alarm;
                return carry;
            }, {});
            console.log('alarms', alarms);

            const updateAndonFunc = {
                RUNNING: updateAndonAsRunning,
                CHANGEOVER: updateAndonAsChangeover,
                BREAKDOWN: updateAndonAsBreakdown,
                COMPLETE: updateAndonAsComplete,
            };

            Echo.join('alarm')
                .listen('SensorAlarmNotification', (data) => {
                    // アラーム通知イベント
                    const alarm = alarms[data.process_id];
                    const smallBox = smallBoxes[data.process_id];
                    if (alarm && smallBox) {
                        console.log('SensorAlarmNotification', data);
                        if (data.is_start) {
                            alarm.events.push(data);
                            showAlarm(smallBox, alarm);
                        } else {
                            alarm.events = alarm.events.filter(x => x.sensor_id !== data.sensor_id);
                            if (alarm.events.length === 0) {
                                if (alarm.intervalId) {
                                    clearInterval(alarm.intervalId);
                                    delete alarm.intervalId;
                                    delete alarm.sensorId;
                                }
                                const fn = updateAndonFunc[alarm.status];
                                const payload = payloads[data.process_id];
                                fn & fn(smallBox, payload);
                            } else {
                                showAlarm(smallBox, alarm);
                            }
                        }
                    }
                });

            Echo.join('summary')
                .listen('ProductionSummaryNotification', (data) => {
                    if (data.indicator === false) {
                        return;
                    }
                    const payload = new Payload(data);
                    const processId = payload.processId;
                    const smallBox = smallBoxes[processId];
                    const fn = updateAndonFunc[payload.statusName];
                    const alarm = alarms[processId];
                    payloads[data.process_id] = payload;
                    console.log('ProductionSummaryNotification', payload);
                    if (smallBox && fn && alarm) {
                        disableSlickAnimation();
                        showAlarm(smallBox, alarm, payload.statusName) || fn(smallBox, payload);
                        enableSlickAnimation(slideSpeed);
                    }
                });

            $('[data-card-widget="maximize"], [data-widget="pushmenu"]').on('click', (event) => {
                disableSlickAnimation();
                setTimeout(() => {
                    enableSlickAnimation(slideSpeed);
                }, 300);
            });

            if ($('.small-box>.inner>h3').length) {
                $('.small-box>.inner>h3').css('font-size', config.font_ratio * 2.2 + 'rem');
            }
            if ($('.small-box>.inner>h5').length) {
                $('.small-box>.inner>h5').css('font-size', config.font_ratio * 1.25 + 'rem');
            }
            if ($('.description-block>.description-header').length) {
                $('.description-block>.description-header').css('font-size', config.font_ratio * 16 + 'px');
            }
            if ($('.description-block>.description-text>span').length) {
                $('.description-block>.description-text>span').css('font-size', config.font_ratio + 'rem');
            }

            function isUpdateIndicator() {
                return config.is_show_good_rate ||
                    config.is_show_good_count ||
                    config.is_show_defective_rate ||
                    config.is_show_defective_count;
            }

            function enableSlickAnimation(speed) {
                slider.slick('slickSetOption', {
                    autoplay: true,
                    speed
                }, true);
            }

            function disableSlickAnimation() {
                slider.slick('slickSetOption', {
                    autoplay: false,
                    speed: 0
                }, false);
            }

            function showAlarm(smallBox, alarm, status, process) {
                if (status) {
                    alarm.status = status;
                }
                if (process && process.production_history == null) {
                    return;
                }
                if (alarm.events.length) {
                    if (alarm.intervalId) {
                        clearInterval(alarm.intervalId);
                        delete alarm.intervalId;
                    }
                    if (alarm.events.length === 1) {
                        const before = alarm.sensorId;
                        const after = alarm.events[0].sensor_id;
                        alarm.sensorId = after;
                        smallBox.update({
                            title: alarm.events[0].alarm_text,
                            icon: 'fas fa-ban',
                            theme: 'orange',
                        });
                        if (before != null && before != after) {
                            $(`#process-${alarm.processId}>.inner>h3`).hide().fadeIn();
                        }
                    } else {
                        alarm.intervalId = setInterval(() => {
                            const idx = alarm.events.findIndex(e => e.sensor_id === alarm.sensorId);
                            const next = (idx + 1) % alarm.events.length;
                            alarm.sensorId = alarm.events[next].sensor_id;
                            smallBox.update({
                                title: alarm.events[next].alarm_text,
                                icon: 'fas fa-ban',
                                theme: 'orange',
                            });
                            $(`#process-${alarm.processId}>.inner>h3`).hide().fadeIn();
                        }, 5000);
                    }
                    return true;
                } else {
                    return false;
                }
            }

            function updateAndonAsRunning(smallBox, payload) {
                const title = @json(__('yokakit.running'));
                smallBox.update({
                    title,
                    icon: 'fas fa-info',
                    theme: 'white',
                });
                updateDisplayItem(payload);
            }

            function updateAndonAsChangeover(smallBox, payload) {
                smallBox.update({
                    title: @json(__('yokakit.changeover')),
                    icon: 'fas fa-triangle-exclamation',
                    theme: 'warning',
                });
                updateDisplayItem(payload);
            }

            function updateAndonAsBreakdown(smallBox, payload) {
                if (payload.inPlannedOutage) {
                    smallBox.update({
                        title: @json(__('yokakit.planned_outage')),
                        icon: 'fas fa-info',
                        theme: 'info',
                    });
                } else {
                    smallBox.update({
                        title: @json(__('yokakit.breakdown')),
                        icon: 'fas fa-ban',
                        theme: 'danger',
                    });
                }
                updateDisplayItem(payload);
            }

            function updateAndonAsComplete(smallBox, payload) {
                smallBox.update({
                    title: @json(__('yokakit.stop')),
                });
                smallBox.remove('icon', 'theme');
                updateDisplayItem(payload);
            }

            function updateDisplayItem(payload) {
                if (payload) {
                    console.log('ここ', payload);
                    updatePartNumberName(payload);
                    updateStart(payload);
                    updateGoodCount(payload);
                    updateGoodRate(payload);
                    updateDefectiveCount(payload);
                    updateDefectiveRate(payload);
                    updatePlanCount(payload);
                    updateAchievementRate(payload);
                    updateCycleTime(payload);
                    updateTimeOperatingRate(payload);
                    updatePerformanceOperatingRate(payload);
                    updateOverallEquipmentEffectiveness(payload);
                    updateGoal(payload);
                }
            }

            const blankText = '--';

            function updatePartNumberName(payload) {
                if (config.is_show_part_number) {
                    const key = `#part-number-${payload.processId} .description-text`;
                    if (payload.isComplete()) {
                        $(key).text(blankText);
                    } else {
                        $(key).text(payload.partNumberName);
                    }
                }
            }

            function updateStart(payload) {
                if (config.is_show_start) {
                    const key = `#start-${payload.processId} .description-text`;
                    if (payload.isComplete() || !payload.start) {
                        $(key).text(blankText);
                    } else {
                        try {
                            $(key).text(payload.start.format('YYYY-MM-DD HH:mm:ss'));
                        } catch (e) {
                            $(key).text(blankText);
                        }
                    }
                }
            }

            function updateGoodCount(payload) {
                if (config.is_show_good_count) {
                    const key = `#good-count-${payload.processId} .description-text`;
                    if (payload.isComplete()) {
                        $(key).text(blankText);
                    } else {
                        $(key).text(payload.goodCount());
                    }
                }
            }

            function updateGoodRate(payload) {
                if (config.is_show_good_rate) {
                    const key = `#good-rate-${payload.processId} .description-text`;
                    if (payload.isComplete()) {
                        $(key).text(blankText);
                    } else {
                        $(key).text(`${payload.goodRate().rate()} [%]`);
                    }
                }
            }

            function updateDefectiveCount(payload) {
                if (config.is_show_defective_count) {
                    const key = `#defective-count-${payload.processId} .description-text`;
                    if (payload.isComplete()) {
                        $(key).text(blankText);
                    } else {
                        $(key).text(payload.defectiveCount());
                    }
                }
            }

            function updateDefectiveRate(payload) {
                if (config.is_show_defective_rate) {
                    const key = `#defective-rate-${payload.processId} .description-text`;
                    if (payload.isComplete()) {
                        $(key).text(blankText);
                    } else {
                        $(key).text(`${payload.defectiveRate().rate()} [%]`);
                    }
                }
            }

            function updatePlanCount(payload) {
                if (config.is_show_plan_count) {
                    const key = `#plan-count-${payload.processId} .description-text`;
                    if (payload.isComplete()) {
                        $(key).text(blankText);
                    } else {
                        $(key).text(payload.planCount());
                    }
                }
            }

            function updateAchievementRate(payload) {
                if (config.is_show_achievement_rate) {
                    const key = `#achievement-rate-${payload.processId} .description-text`;
                    if (payload.isComplete()) {
                        $(key).text(blankText);
                    } else {
                        $(key).text(`${payload.achievementRate().rate()} [%]`);
                    }
                }
            }

            function updateCycleTime(payload) {
                if (config.is_show_cycle_time) {
                    const key = `#cycle-time-${payload.processId} .description-text`;
                    if (payload.isComplete()) {
                        $(key).text(blankText);
                    } else {
                        $(key).text(`${Math.round(payload.cycleTime())} [SEC]`);
                    }
                }
            }

            function updateTimeOperatingRate(payload) {
                if (config.is_show_time_operating_rate) {
                    const key = `#time-operating-rate-${payload.processId} .description-text`;
                    if (payload.isComplete()) {
                        $(key).text(blankText);
                    } else {
                        $(key).text(`${payload.timeOperatingRate().rate()} [%]`);
                    }
                }
            }

            function updatePerformanceOperatingRate(payload) {
                if (config.is_show_performance_operating_rate) {
                    const key = `#performance-operating-rate-${payload.processId} .description-text`;
                    if (payload.isComplete()) {
                        $(key).text(blankText);
                    } else {
                        $(key).text(`${payload.performanceOperatingRate().rate()} [%]`);
                    }
                }
            }

            function updateOverallEquipmentEffectiveness(payload) {
                if (config.is_show_overall_equipment_effectiveness) {
                    const key = `#oee-${payload.processId} .description-text`;
                    if (payload.isComplete()) {
                        $(key).text(blankText);
                    } else {
                        $(key).text(`${payload.overallEquipmentEffectiveness().rate()} [%]`);
                    }
                }
            }

            function updateGoal(payload) {
                if (config.is_show_goal) {
                    const key = `#goal-${payload.processId} .description-text`;
                    if (payload.isComplete()) {
                        $(key).text(blankText);
                    } else {
                        $(key).text(payload.goal);
                    }
                }
            }
        });
    </script>
@endpush
