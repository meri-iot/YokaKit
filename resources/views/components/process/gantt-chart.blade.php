<div class="position-relative float-right pr-2" style="top:-2.75rem; height: 0;">
    @can('admin')
        <a class="btn btn-tool pb-0 pt-0" href="{{ route('gantt-chart.index', ['process' => $process]) }}" role="button">
            <i class="fa-solid fa-lg fa-chart-gantt"></i>
        </a>
        <a class="btn btn-tool pb-0 pt-0" href="{{ route('gantt-chart.sorting', ['process' => $process]) }}" role="button">
            <i class="fa-solid fa-lg fa-sort"></i>
        </a>
        <x-button-add class="pb-0 pt-0" href="{{ route('gantt-chart.create', ['process' => $process]) }}" />
    @endcan
</div>
<table class="mt-2 table">
    <thead>
        <tr>
            <th class="border-top-0 border-bottom-0">{{ __('yokakit.chart_name') }}</th>
            <th class="border-top-0 border-bottom-0">{{ __('yokakit.color') }}</th>
            <th class="border-top-0 border-bottom-0">{{ __('yokakit.raspberry_pi') }}</th>
            <th class="border-top-0 border-bottom-0">{{ __('yokakit.pin_number') }}</th>
            <th class="border-top-0 border-bottom-0">{{ __('yokakit.gantt_chart_type') }}</th>
            <th class="border-top-0 border-bottom-0">{{ __('yokakit.trigger') }}</th>
            @can('admin')
                @if ($process->isStopped())
                    <th class="border-top-0 border-bottom-0 w-1"></th>
                @endif
            @endcan
        </tr>
    </thead>
    <tbody>
        @foreach ($process->ganttCharts as $ganttChart)
            <tr class="text-muted">
                <td class="align-middle">{{ $ganttChart->chart_name }}</td>
                <td class="align-middle">
                    {{-- chart_colorはサニタイズ済み想定だが、念のためe()でエスケープ --}}
                    <i class="fa-solid fa-fw fa-square-full" style="color: {{ e($ganttChart->chart_color) }}"></i>
                </td>
                <td class="align-middle">{{ $ganttChart->raspberryPi->raspberry_pi_name }}</td>
                <td class="align-middle">{{ $ganttChart->pinNumber() }}</td>
                <td class="align-middle">{{ $ganttChart->chart_type->description }}</td>
                <td class="align-middle">{{ $ganttChart->trigger ? 'HIGH' : 'LOW' }}</td>
                @can('admin')
                    @if ($process->isStopped())
                        <td class="text-nowrap text-right align-middle">
                            {{-- ガントチャート編集ボタン --}}
                            <x-button-edit href="{{ route('gantt-chart.edit', ['process' => $process, 'ganttChart' => $ganttChart]) }}" />
                            {{-- ガントチャート削除ボタン --}}
                            <x-button-delete target="gantt_{{ $ganttChart->gantt_chart_id }}" />
                        </td>
                        {{-- ガントチャート削除ダイアログ --}}
                        <x-modal-delete id="gantt_{{ $ganttChart->gantt_chart_id }}"
                            action="{{ route('gantt-chart.destroy', ['process' => $process, 'ganttChart' => $ganttChart]) }}">
                            <strong>{{ __('yokakit.confirm_delete', ['target' => __('yokakit.gantt_chart')]) }}</strong>
                            <x-adminlte-card class="mt-4">
                                <strong>{{ __('yokakit.chart_name') }}</strong>
                                <p class="ml-2 mt-1">{{ $ganttChart->chart_name }}</p>
                                <hr>
                                <strong>{{ __('yokakit.color') }}</strong>
                                <p class="ml-2 mt-1">
                                    {{-- chart_colorはサニタイズ済み想定だが、念のためe()でエスケープ --}}
                                    {{ e($ganttChart->chart_color) }}
                                    <i class="fa-solid fa-fw fa-square-full" style="padding-top:1px; color: {{ e($ganttChart->chart_color) }}"></i>
                                </p>
                                <hr>
                                <strong>{{ __('yokakit.raspberry_pi') }}</strong>
                                <p class="ml-2 mt-1">{{ $ganttChart->raspberryPi->raspberry_pi_name }}</p>
                                <hr>
                                <strong>{{ __('yokakit.pin_number') }}</strong>
                                <p class="ml-2 mt-1">{{ $ganttChart->pinNumber() }}</p>
                                <hr>
                                <strong>{{ __('yokakit.gantt_chart_type') }}</strong>
                                <p class="ml-2 mt-1">{{ $ganttChart->chart_type->description }}</p>
                                <hr>
                                <strong>{{ __('yokakit.trigger') }}</strong>
                                <p class="mb-0 ml-2 mt-1">{{ $ganttChart->trigger ? 'HIGH' : 'LOW' }}</p>
                            </x-adminlte-card>
                        </x-modal-delete>
                    @endif
                @endcan
            </tr>
        @endforeach
    </tbody>
</table>
