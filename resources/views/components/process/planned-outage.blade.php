<div class="position-relative float-right pr-2" style="top:-2.75rem; height: 0;">
    @can('admin')
        @if ($process->isStopped())
            <x-button-add class="pb-0 pt-0" href="{{ route('process.planned-outage.create', ['process' => $process]) }}" />
        @endif
    @endcan
</div>
<table class="mt-2 table">
    <thead>
        <tr>
            <th class="border-top-0 border-bottom-0">{{ __('yokakit.name') }}</th>
            <th class="border-top-0 border-bottom-0">{{ __('yokakit.start') }}</th>
            <th class="border-top-0 border-bottom-0">{{ __('yokakit.end') }}</th>
            @can('admin')
                @if ($process->isStopped())
                    <th class="border-top-0 border-bottom-0 w-1"></th>
                @endif
            @endcan
        </tr>
    </thead>
    <tbody>
        @foreach ($process->plannedOutages as $plannedOutage)
            <tr class="text-muted">
                <td class="align-middle">{{ $plannedOutage->planned_outage_name }}</td>
                <td class="align-middle">{{ $plannedOutage->formatStartTime() }}</td>
                <td class="align-middle">{{ $plannedOutage->formatEndTime() }}</td>
                @can('admin')
                    @if ($process->isStopped())
                        <td class="text-nowrap text-right align-middle">
                            {{-- 計画停止時間削除ボタン --}}
                            <x-button-delete target="planned_outage_{{ $plannedOutage->planned_outage_id }}" />
                        </td>
                        {{-- 計画停止時間削除ダイアログ --}}
                        <x-modal-delete id="planned_outage_{{ $plannedOutage->planned_outage_id }}"
                            action="{{ route('process.planned-outage.destroy', ['process' => $process, 'processPlannedOutage' => $plannedOutage->pivot]) }}">
                            <strong>{{ __('yokakit.confirm_delete', ['target' => __('yokakit.process_planned_outage')]) }}</strong>
                            <x-adminlte-card class="mt-4">
                                <strong>{{ __('yokakit.target_name', ['target' => __('yokakit.planned_outage')]) }}</strong>
                                <p class="ml-2 mt-1">{{ $plannedOutage->planned_outage_name }}</p>
                                <hr>
                                <strong>{{ __('yokakit.start_time') }}</strong>
                                <p class="ml-2 mt-1">{{ $plannedOutage->formatStartTime() }}</p>
                                <hr>
                                <strong>{{ __('yokakit.end_time') }}</strong>
                                <p class="mb-0 ml-2 mt-1">{{ $plannedOutage->formatEndTime() }}</p>
                            </x-adminlte-card>
                        </x-modal-delete>
                    @endif
                @endcan
            </tr>
        @endforeach
    </tbody>
</table>
