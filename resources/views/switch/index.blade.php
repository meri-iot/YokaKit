@extends('components.header')

@section('title', __('yokakit.switch_part_number') . ' & ' . __('yokakit.switch_producer'))

@section('content')
    @php
        $processOptions = $processes->reduce(function ($carry, $process) {
            $carry[$process->process_id] = $process->process_name;
            return $carry;
        }, []);
        $routes = $processes->reduce(
            function ($carry, $process) {
                $processId = $process->process_id;
                $carry['stop'][$processId] = route('switch.stop', ['process' => $process]);
                $carry['changeover'][$processId] = route('switch.start_changeover', ['process' => $process]);
                $carry['restart'][$processId] = route('switch.stop_changeover', ['process' => $process]);
                $carry['switch'][$processId] = route('switch.store', ['process' => $process]);
                $carry['worker'][$processId] = route('switch.change_worker', ['process' => $process]);
                return $carry;
            },
            [
                'stop' => [],
                'changeover' => [],
                'restart' => [],
                'switch' => [],
                'worker' => [],
            ],
        );
        $shares = $processes->reduce(function ($carry, $process) {
            $processId = $process->process_id;
            $carry[$processId] = route('process.show', ['process' => $process]);
            return $carry;
        }, []);

        $errorkv = [];
        foreach ($errors->getMessages() as $key => $message) {
            $errorkv[$key] = $message[0];
        }

        $olds = [];
        foreach ($processes as $process) {
            foreach ($process->lines as $line) {
                $olds[$line->line_id] = old("lines.{$line->line_id}.worker_id");
            }
        }

    @endphp
    @include('adminlte::partials.common.preloader')
    <div class="row">
        <div class="col-lg-6">
            <x-adminlte-card title="{{ __('yokakit.process') }}" body-class="card-body-height">
                <x-adminlte-select class="large" id="select-processes" name="select-processes">
                    <x-adminlte-options :options="$processOptions" selected="{{ $initialId }}" />
                </x-adminlte-select>
                <div class="mt-4 pb-2">
                    <span class="large align-middle">
                        {{ __('yokakit.status') }}
                        <span class="badge float-right" id="production-status" style="font-size: 100%;">--</span>
                    </span>
                    <hr>
                    <span class="large align-middle">
                        {{ __('yokakit.part_number') }}
                        <span class="float-right" id="production-part-number">--</span>
                    </span>
                    <hr>
                    <span class="large align-middle">
                        {{ __('yokakit.start_time') }}
                        <span class="float-right" id="production-start">--</span>
                    </span>
                </div>
                <div class="row">
                    <div class="col-6">
                        {{-- 開始ボタン --}}
                        <x-adminlte-button class="btn-primary large btn-block mt-2" id="btn-restart" data-toggle="modal" data-target="#modal-restart"
                            type="button" style="display: none;" label="{{ __('yokakit.start') }}" icon="fa-solid fa-fw fa-play" />
                        <x-adminlte-modal id="modal-restart" title="{{ __('yokakit.confirm') }}" theme="info" icon="fa-solid fa-fw fa-info" v-centered>
                            <strong>{{ __('yokakit.confirm_production') }}</strong>
                            <x-adminlte-card class="mt-4">
                                <strong>{{ __('yokakit.target_name', ['target' => __('yokakit.process')]) }}</strong>
                                <p class="modal-processs ml-2 mt-1"></p>
                                <hr>
                                <strong>{{ __('yokakit.target_name', ['target' => __('yokakit.part_number')]) }}</strong>
                                <p class="modal-part-number ml-2 mt-1"></p>
                            </x-adminlte-card>
                            <x-slot name="footerSlot">
                                <form id="restart-form" action="#" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <x-adminlte-button class="large" type="submit" theme="info" label="{{ __('yokakit.start_production') }}"
                                        icon="fa-solid fa-fw fa-play" />
                                </form>
                            </x-slot>
                        </x-adminlte-modal>
                        {{-- 段取替えボタン --}}
                        <x-adminlte-button class="btn-primary large btn-block mt-2" id="btn-changeover" data-toggle="modal"
                            data-target="#modal-changeover" type="button" type="button" style="display: none;" label="{{ __('yokakit.changeover') }}"
                            icon="fa-solid fa-fw fa-pause" />
                        <x-adminlte-modal id="modal-changeover" title="{{ __('yokakit.confirm') }}" theme="warning"
                            icon="fa-solid fa-fw fa-triangle-exclamation" v-centered>
                            <strong>{{ __('yokakit.confirm_changeover') }}</strong>
                            <x-adminlte-card class="mt-4">
                                <strong>{{ __('yokakit.target_name', ['target' => __('yokakit.process')]) }}</strong>
                                <p class="modal-processs ml-2 mt-1"></p>
                                <hr>
                                <strong>{{ __('yokakit.target_name', ['target' => __('yokakit.part_number')]) }}</strong>
                                <p class="modal-part-number ml-2 mt-1"></p>
                            </x-adminlte-card>
                            <x-slot name="footerSlot">
                                <form id="changeover-form" action="#" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <x-adminlte-button class="large" type="submit" theme="warning" label="{{ __('yokakit.changeover') }}"
                                        icon="fa-solid fa-fw fa-pause" />
                                </form>
                            </x-slot>
                        </x-adminlte-modal>
                    </div>
                    <div class="col-6">
                        {{-- 停止ボタン --}}
                        <x-adminlte-button class="btn-primary large btn-block mt-2" id="btn-stop" data-toggle="modal" data-target="#modal-stop"
                            type="button" style="display: none;" label="{{ __('yokakit.stop') }}" icon="fa-solid fa-fw fa-stop" />
                        <x-adminlte-modal id="modal-stop" title="{{ __('yokakit.confirm') }}" theme="danger" icon="fa-solid fa-fw fa-ban" v-centered>
                            <strong>{{ __('yokakit.confirm_stop') }}</strong>
                            <x-adminlte-card class="mt-4">
                                <strong>{{ __('yokakit.target_name', ['target' => __('yokakit.process')]) }}</strong>
                                <p class="modal-processs ml-2 mt-1"></p>
                                <hr>
                                <strong>{{ __('yokakit.target_name', ['target' => __('yokakit.part_number')]) }}</strong>
                                <p class="modal-part-number ml-2 mt-1"></p>
                            </x-adminlte-card>
                            <x-slot name="footerSlot">
                                <form id="stop-form" action="#" method="POST">
                                    @csrf
                                    @method('PUT')
                                    <x-adminlte-button class="large" type="submit" theme="danger" label="{{ __('yokakit.stop') }}"
                                        icon="fa-solid fa-fw fa-stop" />
                                </form>
                            </x-slot>
                        </x-adminlte-modal>
                    </div>
                </div>
            </x-adminlte-card>
        </div>
        <div class="col-lg-6">
            <x-adminlte-card title="{{ __('yokakit.part_number') }}" body-class="card-body-height">
                <form id="switch-form" action="#" method="POST">
                    @csrf
                    <x-adminlte-select class="large" id="part_number_id" name="part_number_id" />
                    <x-adminlte-input id="goal" name="goal" igroup-size="lg" label="{{ __('yokakit.goal') }}" enable-old-support>
                        <x-slot name="prependSlot">
                            <div class="input-group-text bg-light">
                                <i class="fa-solid fa-fw fa-bullseye"></i>
                            </div>
                        </x-slot>
                    </x-adminlte-input>
                    <x-adminlte-input-switch id="changeover" name="changeover" data-on-text="YES" data-off-text="NO" data-on-color="info"
                        igroup-size="lg" label="{{ __('yokakit.changeover') }}" checked />
                    {{-- 品番切り替えボタン --}}
                    <x-adminlte-button class="btn-primary large btn-block mt-4" id="btn-switch" data-toggle="modal" data-target="#modal-switch"
                        type="button" label="{{ __('yokakit.switch_part_number') }}" icon="fa-solid fa-fw fa-rotate" />
                    <x-adminlte-modal id="modal-switch" title="{{ __('yokakit.confirm') }}" theme="info" icon="fa-solid fa-fw fa-info" v-centered>
                        <strong>{{ __('yokakit.confirm_switch') }}</strong>
                        <x-adminlte-card class="mt-4">
                            <strong>{{ __('yokakit.target_name', ['target' => __('yokakit.process')]) }}</strong>
                            <p class="modal-processs ml-2 mt-1"></p>
                            <hr>
                            <strong>{{ __('yokakit.target_name', ['target' => __('yokakit.part_number')]) }}</strong>
                            <p class="ml-2 mt-1" id="selected-part-number"></p>
                        </x-adminlte-card>
                        <x-slot name="footerSlot">
                            <x-adminlte-button class="large" type="submit" theme="info" label="{{ __('yokakit.switch_part_number') }}"
                                icon="fa-solid fa-fw fa-rotate" />
                        </x-slot>
                    </x-adminlte-modal>
                </form>
            </x-adminlte-card>
        </div>
        <div class="col-12">
            <x-adminlte-card title="{{ __('yokakit.line') }}" body-class="p-0">
                <form id="worker-form" action="#" method="POST">
                    @csrf
                    @method('PUT')
                    <div class="form-group">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th class="border-top-0 border-bottom-0">{{ __('yokakit.target_name', ['target' => __('yokakit.line')]) }}</th>
                                    <th class="border-top-0 border-bottom-0">{{ __('yokakit.color') }}</th>
                                    <th class="border-top-0 border-bottom-0">{{ __('yokakit.worker') }} ({{ __('yokakit.current') }})</th>
                                    <th class="border-top-0 border-bottom-0">{{ __('yokakit.worker') }} ({{ __('yokakit.new') }})</th>
                                    <th class="border-top-0 border-bottom-0 w-1"></th>
                                    <th class="border-top-0 border-bottom-0"></th>
                                </tr>
                            </thead>
                            <tbody id="line-body">
                            </tbody>
                        </table>
                    </div>
                </form>
            </x-adminlte-card>
        </div>
    </div>
@endsection

@push('css')
    <style>
        .large {
            height: 3rem;
            font-size: 1.5rem;
        }

        .card-body-height {
            height: 360px !important;
        }
    </style>
@endpush

@section('js')
    @include('components.toast')
@endsection

@push('js')
    <script>
        $(() => {
            // 工程一覧
            const processes = @json($processes);
            const plannedOutages = @json($plannedOutages);

            processes.forEach(x => {
                if (x.production_history) {
                    x.production_history.in_planned_outage = plannedOutages[x.process_id];
                } else {
                    x.production_history = {
                        production_history_id: 0,
                        process_name: '',
                        part_number_id: 0,
                        part_number_name: '',
                        status_name: 'COMPLETE',
                        start: moment(),
                        in_planned_outage: false
                    };
                }
            });
            console.log('processes', processes);
            console.log('plannedOutages', plannedOutages);

            // 作業者一覧
            const workers = @json($workers).reduce((carry, x) => {
                carry[x.worker_id] = x;
                return carry;
            }, {});
            console.log('workers', workers);

            // エラー
            const errors = @json($errorkv);
            const olds = @json($olds);
            console.log('errors', errors);
            console.log('olds', olds);

            // route
            const routes = @json($routes);

            // share
            const shares = @json($shares);

            // 選択中の工程
            let selectedProcess = processes.find(x => x.process_id === parseInt(@json($initialId))) || processes[0];

            // ステータスを更新
            updateProcessStatus(selectedProcess);

            // ラインを更新
            createWorkerSelectHtml(selectedProcess);

            // 品番一覧を更新
            updatePartNumbers(selectedProcess);

            // 工程変更イベント
            $('#select-processes').on('change', (event) => {
                // 工程の検索
                const processId = parseInt($(event.target).val());
                const process = processes.find(x => x.process_id === processId);
                if (process) {
                    // 選択工程を変更
                    selectedProcess = process;
                    // ステータスを更新
                    updateProcessStatus(process);
                    // ラインを更新
                    createWorkerSelectHtml(process);
                    // 品番一覧を更新
                    updatePartNumbers(process);
                }
            });

            // 品番変更イベント
            $('#part_number_id').on('change', (event) => {
                const partNumberId = parseInt($(event.target).val());
                const partNumberName = $(event.target).children(':selected').text().trim();
                $('#selected-part-number').text(partNumberName);
                $('#switch-form').attr('action', routes.switch[selectedProcess.process_id]);
            });

            // ステータス変更通知
            Echo.join('summary')
                .listen('ProductionSummaryNotification', (data) => {
                    const process = processes.find(x => x.process_id === data.processId);
                    if (process && selectedProcess.process_id === data.processId) {
                        process.production_history = {
                            process_name: data.processName,
                            part_number_id: data.partNumberId,
                            part_number_name: data.partNumberName,
                            status_name: data.statusName,
                            start: data.start,
                            in_planned_outage: data.inPlannedOutage,
                        };
                        console.log('ProductionSummaryNotification', data);
                        updateProcessStatus(process);
                        createWorkerSelectHtml(process);
                        updatePartNumbers(process);
                    }
                });

            function updatePartNumbers(process) {

                // 品番のセレクトボックスを入れ替え
                $('#part_number_id').children().remove();
                let firstName;
                const history = process.production_history;
                for (const partNumber of process.part_numbers) {
                    if (history.status_name === 'COMPLETE' || partNumber.part_number_id !== history.part_number_id) {
                        const option = $('<option>')
                            .attr('value', partNumber.part_number_id)
                            .text(partNumber.part_number_name);
                        $('#part_number_id').append(option);
                        firstName = firstName || partNumber.part_number_name;
                    }
                }

                if (process.part_numbers[0]) {
                    $('#btn-switch').attr('disabled', false);
                    $('#selected-part-number').text(firstName);
                    $('#switch-form').attr('action', routes.switch[process.process_id]);
                } else {
                    $('#btn-switch').attr('disabled', true);
                }
            }

            function updateProcessStatus(process) {

                if (process == null) {
                    return;
                }

                // セレクトボックスの値を設定
                $('#select-processes').val(process.process_id);
                $('#worker-form').attr('action', routes.worker[process.process_id]);

                // ボタン
                const restartButton = $('#btn-restart');
                const changeoverButton = $('#btn-changeover');
                const stopButton = $('#btn-stop');

                // テキスト
                const stopText = @json(__('yokakit.stop'));
                const runningText = @json(__('yokakit.running'));
                const breakdownText = @json(__('yokakit.breakdown'));
                const changeoverText = @json(__('yokakit.changeover'));
                const plannedOutageText = @json(__('yokakit.planned_outage'));

                // ステータス
                const productionStatus = $('#production-status');
                const productionStart = $('#production-start');
                const productionPartNumber = $('#production-part-number');

                const history = process.production_history;
                if (history.status_name === 'COMPLETE') {
                    changeoverButton.hide();
                    restartButton.hide();
                    stopButton.hide();
                    productionStatus
                        .removeClass()
                        .addClass('float-right')
                        .text(stopText);
                    productionStart.text('--');
                    productionPartNumber.text('--');
                    $('.modal-processs').text(process.process_name);
                    $('.modal-part-number').text('');
                } else {
                    const processId = process.process_id;
                    const status = history.status_name;
                    const partNumber = history.part_number_name;
                    const start = moment(history.start).format('YYYY-MM-DD HH:mm:ss');
                    if (status === 'RUNNING') {
                        changeoverButton.show();
                        restartButton.hide();
                        productionStatus
                            .removeClass()
                            .addClass('badge badge-light float-right')
                            .text(runningText);
                    } else if (status === 'BREAKDOWN') {
                        changeoverButton.show();
                        restartButton.hide();
                        if (history.in_planned_outage) {
                            productionStatus
                                .removeClass()
                                .addClass('badge badge-info float-right')
                                .text(plannedOutageText);
                        } else {
                            productionStatus
                                .removeClass()
                                .addClass('badge badge-danger float-right')
                                .text(breakdownText);
                        }
                    } else if (status === 'CHANGEOVER') {
                        changeoverButton.hide();
                        restartButton.show();
                        productionStatus
                            .removeClass()
                            .addClass('badge badge-warning float-right')
                            .text(changeoverText);
                    }
                    $('.modal-processs').text(history.process_name);
                    $('.modal-part-number').text(history.part_number_name);
                    productionStart.text(start);
                    productionPartNumber.text(partNumber);
                    $('#stop-form').attr('action', routes.stop[processId]);
                    $('#changeover-form').attr('action', routes.changeover[processId]);
                    $('#restart-form').attr('action', routes.restart[processId]);
                    stopButton.show();
                }
            }

            function createWorkerSelectHtml(process) {
                if (process == null) {
                    return;
                }
                const nonDefectiveLines = process.lines.filter(x => x.defective === false);
                const body = $('#line-body').empty();
                for (const line of nonDefectiveLines) {
                    // テーブル行
                    const tr = $('<tr>');
                    // ライン名
                    tr.append(createLineNameHtml(line));
                    // 色
                    tr.append(createColorHtml(line));
                    // 現在の作業者
                    tr.append(createCurrentWorkerHtml(line));
                    // 新規作業者
                    tr.append(createNewWorkerSelectorHtml(line));
                    // ダミー
                    tr.append($('<td>').addClass('p-0'));
                    // ボタン
                    if (line === nonDefectiveLines[0]) {
                        tr.append(createSwitchWorkerButton(nonDefectiveLines.length));
                    }
                    body.append(tr);
                }
            }

            function createLineNameHtml(line) {
                const lineId = line.line_id;
                return $('<td>')
                    .text(line.line_name)
                    .addClass('large align-middle')
                    .append($('<input>').attr({
                        type: 'hidden',
                        name: `lines[${lineId}][line_id]`,
                        value: lineId,
                    }))
                    .append($('<input>').attr({
                        type: 'hidden',
                        name: `lines[${lineId}][raspberry_pi_id]`,
                        value: line.raspberry_pi_id,
                    }));
            }

            function createColorHtml(line) {
                return $('<td>')
                    .addClass('large align-middle')
                    .append($('<i>')
                        .addClass('fa-solid fa-fw fa-square-full')
                        .css('color', line.chart_color));
            }

            function createCurrentWorkerHtml(line) {
                const td = $('<td>');
                if (workers[line.worker_id]) {
                    const worker = workers[line.worker_id];
                    const text = `${worker.identification_number}：${worker.worker_name}`;
                    td.addClass('large align-middle').text(text);
                }
                return td;
            }

            function createSwitchWorkerButton(length) {
                const button = $('<button>')
                    .addClass('btn btn-default btn-primary large btn-block')
                    .attr('type', 'submit')
                    .css('height', `${length*2}em`)
                    .append($('<i>').addClass('fa-solid fa-fw fa-rotate'))
                    .append(@json(__('yokakit.update')));
                const td = $('<td>').attr('rowspan', length).addClass('align-middle');
                return td.append(button);
            }

            function createNewWorkerSelectorHtml(line) {
                const lineId = line.line_id;
                const inputGroup = $('<div>').addClass('input-group');
                const select = $('<select>')
                    .attr('name', `lines[${lineId}][worker_id]`)
                    .attr('id', `lines[${lineId}][worker_id]`)
                    .addClass('form-control large');
                select.append($('<option>').attr('value', ''));
                let isSelected = false;
                for (const worker of Object.values(workers)) {
                    const option = $('<option>')
                        .attr('value', worker.worker_id)
                        .text(`${worker.identification_number}：${worker.worker_name}`);
                    if (olds[lineId] === worker.worker_id.toString()) {
                        option.prop('selected', true);
                        isSelected = true;
                    } else if (line.worker_id === worker.worker_id && !isSelected) {
                        option.prop('selected', true);
                    }
                    select.append(option);
                }
                inputGroup.append(select);
                const error = generateValidateErrorHtml(lineId);
                if (error) {
                    inputGroup.append(error);
                }
                return $('<td>').addClass('large align-middle').append(inputGroup);
            }

            function generateValidateErrorHtml(lineId) {
                const keys = ['line_id', 'worker_id', 'raspberry_pi_id'];
                for (const key of keys) {
                    const _key = `lines.${lineId}.${key}`;
                    if (errors[_key]) {
                        const strong = $('<strong>').text(errors[_key]);
                        return $('<span>').addClass('invalid-feedback d-block').attr('role', 'aleart').append(strong);
                    }
                }
            }

            function findWorker(identificationNumber, workerName) {
                return Object.values(workers).find(x => x.identification_number === identificationNumber && x.worker_name === workerName);
            }
        });
    </script>
@endpush
