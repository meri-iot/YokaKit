<div class="position-relative float-right pr-2" style="top:-2.75rem; height: 0;">
    @can('admin')
        @if ($process->isStopped())
            <x-button-add class="pb-0 pt-0" href="{{ route('cycle-time.create', ['process' => $process]) }}" />
        @endif
    @endcan
</div>
<table class="mt-2 table">
    <thead>
        <tr>
            <th class="border-top-0 border-bottom-0">
                {{ __('yokakit.target_name', ['target' => __('yokakit.part_number')]) }}
            </th>
            <th class="border-top-0 border-bottom-0">
                {{ __('yokakit.barcode') }}
            </th>
            <th class="border-top-0 border-bottom-0">
                {{ __('yokakit.standard_cycle_time') }}{{ __('yokakit.unit_sec') }}
            </th>
            <th class="border-top-0 border-bottom-0">
                {{ __('yokakit.over_time') }}{{ __('yokakit.unit_sec') }}
            </th>
            @can('admin')
                @if ($process->isStopped())
                    <th class="border-top-0 border-bottom-0 w-1"></th>
                @endif
            @endcan
        </tr>
    </thead>
    <tbody>
        @foreach ($process->partNumbers as $partNumber)
            <tr class="text-muted">
                <td class="align-middle">{{ $partNumber->part_number_name }}</td>
                <td class="align-middle">{{ $partNumber->barcode }}</td>
                <td class="align-middle">{{ $partNumber->pivot->cycle_time }}</td>
                <td class="align-middle">{{ $partNumber->pivot->over_time }}</td>
                @can('admin')
                    @if ($process->isStopped())
                        <td class="text-nowrap text-right align-middle">
                            {{-- サイクルタイム編集ボタン --}}
                            <x-button-edit href="{{ route('cycle-time.edit', ['process' => $process, 'cycleTime' => $partNumber->pivot]) }}" />
                            {{-- サイクルタイム削除ボタン --}}
                            <x-button-delete target="cycle_time_{{ $partNumber->pivot->cycle_time_id }}" />
                        </td>
                        {{-- サイクルタイム削除ダイアログ --}}
                        <x-modal-delete id="cycle_time_{{ $partNumber->pivot->cycle_time_id }}"
                            action="{{ route('cycle-time.destroy', ['process' => $process, 'cycleTime' => $partNumber->pivot]) }}">
                            <strong>{{ __('yokakit.confirm_delete', ['target' => __('yokakit.cycle_time')]) }}</strong>
                            <x-adminlte-card class="mt-4">
                                <strong>{{ __('yokakit.target_name', ['target' => __('yokakit.part_number')]) }}</strong>
                                <p class="ml-2 mt-1">{{ $partNumber->part_number_name }}</p>
                                <hr>
                                <strong>{{ __('yokakit.standard_cycle_time') }}{{ __('yokakit.unit_sec') }}</strong>
                                <p class="ml-2 mt-1">{{ $partNumber->pivot->cycle_time }}</p>
                                <hr>
                                <strong>{{ __('yokakit.over_time') }}{{ __('yokakit.unit_sec') }}</strong>
                                <p class="mb-0 ml-2 mt-1">{{ $partNumber->pivot->over_time }}</p>
                            </x-adminlte-card>
                        </x-modal-delete>
                    @endif
                @endcan
            </tr>
        @endforeach
    </tbody>
</table>
