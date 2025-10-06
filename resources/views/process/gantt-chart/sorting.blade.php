@extends('components.header', ['breadcrumbs' => $process])

@section('title', __('yokakit.target_sort', ['target' => __('yokakit.gantt_chart')]))

@section('content')
    <div class="row">
        <div class="col-md-12">
            <form action="{{ route('gantt-chart.sort', ['process' => $process]) }}" method="POST" autocomplete="off">
                @csrf
                <x-adminlte-card body-class="p-0">
                    <table class="mb-1 table" id="sortable">
                        <thead>
                            <th class="border-top-0 border-bottom-0"></th>
                            <th class="border-top-0 border-bottom-0">{{ __('yokakit.chart_name') }}</th>
                            <th class="border-top-0 border-bottom-0">{{ __('yokakit.color') }}</th>
                            <th class="border-top-0 border-bottom-0">{{ __('yokakit.raspberry_pi') }}</th>
                            <th class="border-top-0 border-bottom-0">{{ __('yokakit.pin_number') }}</th>
                            <th class="border-top-0 border-bottom-0">{{ __('yokakit.trigger') }}</th>
                        </thead>
                        <tbody>
                            @foreach ($process->ganttCharts as $ganttChart)
                                <tr class="text-muted cursor-move">
                                    <td class="align-middle">
                                        <span class="handle">
                                            <i class="fa-solid fa-grip-vertical text-secondary"></i>
                                        </span>
                                        <input name="order[]" type="hidden" value="{{ $ganttChart->gantt_chart_id }}">
                                    </td>
                                    <td class="align-middle">{{ $ganttChart->chart_name }}</td>
                                    <td class="align-middle">
                                        <i class="fa-solid fa-fw fa-square-full" style="color: {{ $ganttChart->chart_color }}"></i>
                                    </td>
                                    <td class="align-middle">{{ $ganttChart->raspberryPi->raspberry_pi_name }}</td>
                                    <td class="align-middle">{{ $ganttChart->pinNumber() }}</td>
                                    <td class="align-middle">{{ $ganttChart->trigger ? 'HIGH' : 'LOW' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    <x-slot name="footerSlot">
                        <x-adminlte-button type="submit" theme="info" label="{{ __('yokakit.sort') }}" icon="fa-solid fa-fw fa-paper-plane" />
                        <x-button-back href="{{ route('process.show', ['process' => $process, 'tab' => 'gantt-chart']) }}" />
                    </x-slot>
                </x-adminlte-card>
            </form>
        </div>
    </div>
@endsection

@push('js')
    <script>
        /**
         * @see https://qiita.com/qwe001/items/10366df1901853acca5c
         */
        function fixPlaceHolderWidth(event, ui) {
            // adjust placeholder td width to original td width
            ui.children().each(function() {
                $(this).width($(this).width());
            });
            return ui;
        };
        $(() => {
            $('#sortable tbody').sortable({
                axis: 'y',
                opacity: 0.5,
                start: (event, ui) => {
                    ui.placeholder.height(ui.helper.outerHeight());
                },
                helper: fixPlaceHolderWidth
            });
        });
    </script>
@endpush
